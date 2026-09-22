<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Enforcement correspondence.
 *
 * A letter is drafted from a completed inspection, carries the findings
 * forward as the requirements to be corrected, and then travels the
 * approval chain: Director, Senior Inspector, Chief Inspector. Only the
 * Chief signs. The Secretary then assigns the reference number, prints,
 * stamps, uploads the scan and dispatches it to the owner.
 */
class LetterController extends Controller
{
    /** Draft a letter from an inspection. */
    public function create(Inspection $inspection)
    {
        abort_unless(auth()->user()->can('document.draft'), 403);
        abort_unless($inspection->conductedBy(auth()->user()), 403,
            'A letter may only be drafted by an officer who conducted this inspection.');
        abort_unless($inspection->status === 'completed', 422,
            'A letter can only be drafted from a completed inspection.');

        $inspection->load(['entity.upis', 'answers.item', 'template']);

        $failed = $inspection->answers->where('status', 'no')
            ->map(fn ($a) => $a->item?->label)->filter()->values();

        return view('letter.form', [
            'inspection' => $inspection,
            'letter'     => null,
            'failed'     => $failed,
            'types'      => config('letters.types'),
            'action'     => route('letter.store', $inspection),
        ]);
    }

    public function store(Request $request, Inspection $inspection)
    {
        abort_unless(auth()->user()->can('document.draft'), 403);
        abort_unless($inspection->conductedBy(auth()->user()), 403,
            'A letter may only be drafted by an officer who conducted this inspection.');

        $data = $this->validated($request);

        $letter = DB::transaction(function () use ($data, $inspection) {
            return Document::create([
                'type'            => 'letter',
                'letter_type'     => $data['letter_type'],
                'inspection_id'   => $inspection->id,
                'status'          => Document::DRAFT,
                'district_id'     => $inspection->district_id,
                'title'           => $inspection->entity->name,
                'subject'         => $data['subject'],
                'salutation'      => $data['salutation'],
                'deadline_days'   => $data['deadline_days'] ?? null,
                'deadline_date'   => isset($data['deadline_days'])
                                        ? now()->addDays((int) $data['deadline_days'])->toDateString()
                                        : null,
                'prior_reference' => $data['prior_reference'] ?? null,
                'prior_date'      => $data['prior_date'] ?? null,
                'reply_date'      => $data['reply_date'] ?? null,
                'body_html'       => $data['body_html'] ?? null,
                'created_by'      => auth()->id(),
                'current_holder_id' => auth()->id(),
            ]);
        });

        return redirect()->route('letter.show', $letter)
            ->with('status', 'Letter drafted for ' . $inspection->entity->name . '.');
    }

    public function edit(Document $letter)
    {
        abort_unless($letter->isEditable(), 422, 'This letter can no longer be edited.');
        abort_unless(auth()->user()->can('update', $letter), 403);

        $inspection = Inspection::with(['entity.upis', 'answers.item'])->find($letter->inspection_id);

        abort_unless($inspection && $inspection->conductedBy(auth()->user()), 403,
            'This letter may only be edited by an officer who conducted the inspection.');

        $failed = $inspection?->answers->where('status', 'no')
            ->map(fn ($a) => $a->item?->label)->filter()->values() ?? collect();

        return view('letter.form', [
            'inspection' => $inspection,
            'letter'     => $letter,
            'failed'     => $failed,
            'types'      => config('letters.types'),
            'action'     => route('letter.update', $letter),
        ]);
    }

    public function update(Request $request, Document $letter)
    {
        abort_unless($letter->isEditable(), 422, 'This letter can no longer be edited.');
        abort_unless(auth()->user()->can('update', $letter), 403);

        $inspection = Inspection::find($letter->inspection_id);

        abort_unless($inspection && $inspection->conductedBy(auth()->user()), 403,
            'This letter may only be edited by an officer who conducted the inspection.');

        $data = $this->validated($request);

        $letter->fill([
            'letter_type'     => $data['letter_type'],
            'subject'         => $data['subject'],
            'salutation'      => $data['salutation'],
            'deadline_days'   => $data['deadline_days'] ?? null,
            'deadline_date'   => isset($data['deadline_days'])
                                    ? now()->addDays((int) $data['deadline_days'])->toDateString()
                                    : null,
            'prior_reference' => $data['prior_reference'] ?? null,
            'prior_date'      => $data['prior_date'] ?? null,
            'reply_date'      => $data['reply_date'] ?? null,
            'body_html'       => $data['body_html'] ?? null,
        ])->save();

        return redirect()->route('letter.show', $letter)->with('status', 'Letter updated.');
    }

    public function show(Document $letter)
    {
        abort_unless(auth()->user()->can('view', $letter), 403);

        $inspection = Inspection::with(['entity.upis', 'answers.item'])->find($letter->inspection_id);

        return view('letter.show', [
            'letter'     => $letter->load(['transitionLog.actor', 'signatures.signer', 'creator']),
            'inspection' => $inspection,
            'canEdit'    => $letter->isEditable() && auth()->user()->can('update', $letter),
        ]);
    }

    /** Download in the unit's letter format. */
    public function word(Document $letter)
    {
        abort_unless(auth()->user()->can('view', $letter), 403);

        $inspection = Inspection::with(['entity.upis', 'answers.item'])->findOrFail($letter->inspection_id);

        /* The post signs, not a person — whoever holds it. whereHas returns
           nothing if the role is absent, rather than throwing: an empty
           signature block is a visible problem, a blank page is not. */
        $signatory = User::whereHas('roles', fn ($q) =>
                $q->where('name', config('letters.signatory.role', 'Chief Inspector')))
            ->where('is_active', true)
            ->first();

        $name = 'Ibaruwa-' . preg_replace('/[^A-Za-z0-9]+/', '-', $inspection->entity->name)
              . '-' . ($letter->reference_number ?: 'draft') . '.doc';

        /* One letter, one document. The HTML version produced a
           different page layout from the template, which meant the
           document approved on screen was not the document served. */
        return redirect()->route('letter.docx', $letter);

        $html = view('letter.word', compact('letter', 'inspection', 'signatory'))->render();

        return response($html, 200, [
            'Content-Type'        => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'letter_type'     => ['required', 'string', 'in:' . implode(',', array_keys(config('letters.types')))],
            'subject'         => ['required', 'string', 'max:255'],
            'salutation'      => ['required', 'in:male,female,company'],
            'deadline_days'   => ['nullable', 'integer', 'min:1', 'max:365'],
            'prior_reference' => ['nullable', 'string', 'max:60'],
            'prior_date'      => ['nullable', 'date'],
            'reply_date'      => ['nullable', 'date'],
            'body_html'       => ['nullable', 'string'],
        ]);
    }
}
