<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use App\Models\FinePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Penalties.
 *
 * A fine is not proposed by hand. Completing an inspection raises it from
 * the faults recorded, with the amount taken from the sanctions schedule
 * — an officer typing a figure would be exercising a judgement the
 * schedule has already made, and two officers would type different
 * numbers for the same fault.
 *
 * From there, three acts by three people:
 *
 *   The Secretary confirms the fine matches the letter that was served.
 *   A statement of fact, carrying no discretion.
 *
 *   The Chief Inspector, Senior Inspector or Director may reduce or waive
 *   it, between the report being sealed and the letter being signed, with
 *   a reason recorded.
 *
 *   The Recovery Officer records payment, and sees nothing that is not
 *   yet owed.
 */
class FineController extends Controller
{
    /**
     * The fine register.
     *
     * The figures are amounts rather than counts. How many fines are
     * outstanding matters less than how much — "8 pending" tells the
     * Chief Inspector nothing about whether that is two hundred thousand
     * francs or twelve million.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->can('fine.view'), 403);

        $filters = [
            'status'   => $request->query('status'),
            'district' => $request->query('district'),
            'from'     => $request->query('from'),
            'to'       => $request->query('to'),
            'q'        => trim((string) $request->query('q', '')),
        ];

        $query = Fine::with([
                'entity', 'proposer', 'confirmer', 'adjuster',
                'inspection.faults.fault', 'document',
            ])
            ->orderByRaw("FIELD(status,'confirmed','part_paid','proposed','paid','waived','cancelled')")
            ->orderBy('due_date');

        if (! $user->can('inspection.view.all')) {
            $query->where('district_id', $user->district_id);
        }

        /* A proposal is not a debt. The Recovery Officer collects what is
           owed, and a fine not yet confirmed against a served letter is
           owed by nobody — so it does not appear, and does not count
           towards the figures they are working from. */
        if ($this->collectorOnly($user)) {
            $query->whereNotIn('status', [Fine::PROPOSED]);
        }

        if ($filters['district']) {
            $query->whereHas('entity', fn ($q) => $q->where('district', $filters['district']));
        }

        if ($filters['from']) {
            $query->whereDate('proposed_at', '>=', $filters['from']);
        }

        if ($filters['to']) {
            $query->whereDate('proposed_at', '<=', $filters['to']);
        }

        if ($filters['q']) {
            $term = $filters['q'];
            $query->where(fn ($w) => $w
                ->where('reference', 'like', "%{$term}%")
                ->orWhereHas('entity', fn ($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('owner', 'like', "%{$term}%")
                    ->orWhere('upi', 'like', "%{$term}%")
                    ->orWhere('telephone', 'like', "%{$term}%")));
        }

        /* Everything in scope before the status filter: the figures above
           the list describe the whole register, and a filtered total
           sitting under an unfiltered heading would mislead. */
        $all = (clone $query)->get();

        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        $fines     = $query->get();
        $confirmed = $all->whereIn('status', [Fine::CONFIRMED, Fine::PART_PAID]);

        return view('fine.index', [
            'fines'   => $fines,
            'filters' => $filters,
            'active'  => collect($filters)->filter()->count(),

            'figures' => [
                'proposed' => (float) $all->where('status', Fine::PROPOSED)->sum('amount'),
                'approved' => (float) $confirmed->sum('amount'),
                'paid'     => (float) $all->sum('amount_paid'),
                'pending'  => (float) $confirmed->reject->isOverdue()->sum(fn ($f) => $f->outstanding()),
                'overdue'  => (float) $confirmed->filter->isOverdue()->sum(fn ($f) => $f->outstanding()),

                /* How many premises carry a fine, rather than how much.
                   Two fines of six million and one of fifty thousand are a
                   different enforcement picture from thirty of two hundred
                   thousand, and the totals do not show it. */
                'entities' => $all->pluck('entity_id')->filter()->unique()->count(),
            ],

            'counts' => [
                'proposed' => $all->where('status', Fine::PROPOSED)->count(),
                'approved' => $confirmed->count(),
                'paid'     => $all->whereIn('status', [Fine::PAID, Fine::PART_PAID])->count(),
                'pending'  => $confirmed->reject->isOverdue()->count(),
                'overdue'  => $confirmed->filter->isOverdue()->count(),
            ],

            'canConfirm' => $user->can('fine.confirm'),
            'canAdjust'  => $user->can('fine.adjust'),
            'canRecord'  => $user->can('fine.payment.update'),
        ]);
    }

    public function show(Fine $fine)
    {
        $user = auth()->user();
        abort_unless($user->can('fine.view'), 403);

        /* Nothing is owed on a proposal, so the officer who collects has
           no business with one. */
        abort_if($fine->status === Fine::PROPOSED && $this->collectorOnly($user), 403,
            'This fine has not been confirmed, so nothing is yet owed on it.');

        $fine->load([
            'entity', 'inspection.faults.fault', 'document',
            'proposer', 'confirmer', 'adjuster', 'payments.recorder',
        ]);

        return view('fine.show', [
            'fine'       => $fine,
            'canConfirm' => $user->can('fine.confirm') && $fine->status === Fine::PROPOSED,
            'canAdjust'  => $user->can('fine.adjust')  && $fine->isAdjustable(),
            'canRecord'  => $user->can('fine.payment.update') && $fine->isPayable(),
        ]);
    }

    /**
     * The Secretary confirms the fine matches the letter that issued.
     *
     * A statement of fact rather than a decision: nothing to type,
     * nothing to choose. Only the Secretary knows what actually left the
     * building, which is why the act belongs here — and why it carries no
     * discretion over the amount. That comes from the schedule, and a
     * Secretary typing a different figure would make the schedule
     * advisory.
     */
    public function confirm(Request $request, Fine $fine)
    {
        abort_unless(auth()->user()->can('fine.confirm'), 403);
        abort_unless($fine->status === Fine::PROPOSED, 422, 'This fine has already been decided.');

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $fine->forceFill([
            'status'         => Fine::CONFIRMED,
            'confirmed_by'   => auth()->id(),
            'confirmed_at'   => now(),
            'confirm_note'   => $data['note'] ?? null,
            'reference'      => $fine->reference ?: $this->fineReference($fine),
            'case_reference' => $fine->case_reference ?: $fine->inspection?->case_reference,

            /* Where the letter set no date, thirty days from confirmation.
               A fine with no due date can never be overdue, and would sit
               in the register indefinitely without anyone noticing. */
            'due_date'       => $fine->due_date ?: now()->addDays(30),
        ])->save();

        return redirect()->route('fine.show', $fine)
            ->with('status', 'Fine confirmed against the letter. It is now payable.');
    }

    /**
     * Reducing or waiving a fine.
     *
     * Only between the report being sealed and the letter being signed.
     * Before that the faults themselves can be corrected, which is the
     * proper remedy; after it the figure has been stated to the premises,
     * and changing it would leave the City holding one number and the
     * owner another.
     *
     * A reason is required. A fine reduced without one is
     * indistinguishable from a fine recorded wrongly.
     */
    public function adjust(Request $request, Fine $fine)
    {
        abort_unless(auth()->user()->can('fine.adjust'), 403);

        abort_unless($fine->isAdjustable(), 422,
            $fine->letterSigned()
                ? 'The letter has been signed. The figure it states cannot now be changed.'
                : 'The report must be signed before a fine can be adjusted.');

        $data = $request->validate([
            'decision' => ['required', 'in:reduce,waive,cancel'],
            'amount'   => ['required_if:decision,reduce', 'nullable', 'numeric', 'min:1',
                           'lt:' . ((float) $fine->amount)],
            'reason'   => ['required', 'string', 'min:15', 'max:1000'],
        ], [
            'amount.lt'       => 'An adjustment may only reduce a fine, not raise it.',
            'reason.required' => 'Give the reason. A fine changed without one cannot be explained later.',
            'reason.min'      => 'Give enough detail for the decision to stand on its own.',
        ]);

        $fine->forceFill([
            /* What the schedule set, kept. Without it an adjustment is
               invisible: the fine simply reads as a different number from
               the one its faults produce. */
            'original_amount' => $fine->original_amount ?? $fine->amount,

            'amount'          => $data['decision'] === 'reduce' ? $data['amount'] : $fine->amount,
            'status'          => match ($data['decision']) {
                'reduce' => $fine->status,
                'waive'  => Fine::WAIVED,
                'cancel' => Fine::CANCELLED,
            },
            'adjusted_by'     => auth()->id(),
            'adjusted_at'     => now(),
            'adjust_reason'   => $data['reason'],
        ])->save();

        return redirect()->route('fine.show', $fine)->with('status', match ($data['decision']) {
            'reduce' => 'Fine reduced to FRW ' . number_format((float) $data['amount'], 0) . '.',
            'waive'  => 'Fine waived.',
            'cancel' => 'Fine cancelled.',
        });
    }

    /** The Recovery Officer records a payment received. */
    public function pay(Request $request, Fine $fine)
    {
        abort_unless(auth()->user()->can('fine.payment.update'), 403);
        abort_unless($fine->isPayable(), 422, 'This fine is not currently payable.');

        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:1', 'max:' . $fine->outstanding()],
            'paid_on'   => ['required', 'date', 'before_or_equal:today'],
            'method'    => ['required', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:80'],
            'note'      => ['nullable', 'string', 'max:500'],
        ], [
            'amount.max' => 'That is more than the outstanding balance of FRW '
                            . number_format($fine->outstanding()) . '.',
        ]);

        DB::transaction(function () use ($fine, $data) {
            FinePayment::create([
                'fine_id'     => $fine->id,
                'amount'      => $data['amount'],
                'paid_on'     => $data['paid_on'],
                'method'      => $data['method'],
                'reference'   => $data['reference'] ?? null,
                'note'        => $data['note'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            $fine->recorded_by = auth()->id();
            $fine->refreshSettlement();
        });

        return redirect()->route('fine.show', $fine)->with('status',
            'FRW ' . number_format((float) $data['amount']) . ' recorded. '
            . ($fine->fresh()->status === Fine::PAID
                ? 'The fine is now settled in full.'
                : 'FRW ' . number_format($fine->fresh()->outstanding()) . ' remains outstanding.'));
    }

    /**
     * An officer who collects but does not confirm.
     *
     * Asked twice — for the list and for a single fine — so it is settled
     * in one place rather than written out differently each time.
     */
    private function collectorOnly($user): bool
    {
        return $user->can('fine.payment.update') && ! $user->can('fine.confirm');
    }

    /**
     * A fine belongs to a case.
     *
     * Where the case already has a registry reference it is used, with a
     * suffix so several fines on one case remain distinguishable.
     * Otherwise a standalone number is issued, and it joins the case when
     * the letter is referenced.
     */
    private function fineReference(Fine $fine): string
    {
        $case = $fine->inspection?->case_reference;

        if ($case) {
            $n = Fine::where('inspection_id', $fine->inspection_id)
                     ->whereNotNull('reference')->count() + 1;

            return $n > 1 ? $case . '-F' . $n : $case;
        }

        $n = Fine::whereNotNull('reference')->count() + 1;

        return 'CoK/FINE/' . now()->format('Y') . '/' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}