<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\DocumentTransition;
use App\Models\Inspection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moving a letter through the approval chain.
 *
 * Inspector drafts and submits, the Director of the district verifies,
 * the Senior Inspector verifies centrally, the Chief Inspector approves
 * and signs. Any reviewer may return it, with a written reason.
 *
 * Every movement is written to document_transitions, which is insert
 * only. The letter always carries its inspection report, so a reviewer
 * sees the findings the letter rests on rather than the letter alone.
 */
class LetterWorkflowController extends Controller
{
    /** The review screen: letter and its report, side by side. */
    public function review(Document $letter)
    {
        abort_unless(auth()->user()->can('view', $letter), 403);

        $inspection = Inspection::with([
            'entity.upis', 'template.sections.items', 'answers.item', 'team', 'photos', 'inspector',
        ])->findOrFail($letter->inspection_id);

        return view('letter.review', [
            'letter'     => $letter->load(['transitionLog.actor', 'signatures.signer', 'creator']),
            'inspection' => $inspection,
            'answers'    => $inspection->answers->keyBy('item_id'),
            'next'       => $this->nextStage($letter),
            'canAct'     => $this->canAct($letter),
            'canReturn'  => auth()->user()->can('transition', [$letter, Document::RETURNED]),
        ]);
    }

    /** Send a draft to the district Director. */
    public function submit(Request $request, Document $letter)
    {
        $this->authorise($letter, Document::PENDING_DIRECTOR);

        $this->move($letter, Document::PENDING_DIRECTOR, $request->input('comment'));

        /* A letter states findings; the report evidences them. Sending
           one without the other asks the recipient to accept an assertion
           without its basis. */
        $report = Document::where('inspection_id', $letter->inspection_id)
            ->where('type', 'report')
            ->latest('id')->first();

        abort_unless($report && $report->isSealed(), 422,
            'The inspection report must be signed by the team and the Director before this letter can be transmitted.');

        abort_unless($report->sealIntact(), 422,
            'The report has changed since it was signed. It must be re-signed before transmission.');
        return redirect()->route('letter.show', $letter)
            ->with('status', 'Letter submitted to the Director of Inspection for verification.');
    }

    /** Verify and pass to the next stage. */
    public function advance(Request $request, Document $letter)
    {
        $next = $this->nextStage($letter);

        if (! $next) {
            throw ValidationException::withMessages([
                'letter' => 'This letter has no further stage.',
            ]);
        }

        $this->authorise($letter, $next);

        // The Chief Inspector's approval is a signature, not merely a move.
        if ($next === Document::APPROVED) {
            return $this->approve($request, $letter);
        }

        $this->move($letter, $next, $request->input('comment'));

        return redirect()->route('letter.show', $letter)
            ->with('status', 'Letter verified and passed to the next stage.');
    }

    /** Chief Inspector: approve, sign and freeze. */
    public function approve(Request $request, Document $letter)
    {
        $this->authorise($letter, Document::APPROVED);

        $user = auth()->user();

        DB::transaction(function () use ($letter, $user, $request) {

            $hash = $letter->computeHash();

            $this->move($letter, Document::APPROVED, $request->input('comment'), false);

            $letter->forceFill([
                'approved_at'  => now(),
                'content_hash' => $hash,
            ])->save();

            DocumentSignature::create([
                'document_id'      => $letter->id,
                'signer_id'        => $user->id,
                'signer_role'      => $user->roles->pluck('name')->first() ?? 'Chief Inspector',
                'signer_name'      => $user->name,
                'content_hash'     => $hash,
                'signature_method' => 'drawn',
                'ip_address'       => $request->ip(),
            ]);
        });

        return redirect()->route('letter.show', $letter)
            ->with('status', 'Letter approved and signed. It is now frozen and ready for the registry.');
    }

    /** Send back to the drafting inspector with a reason. */
    public function returnForRevision(Request $request, Document $letter)
    {
        $this->authorise($letter, Document::RETURNED);

        $data = $request->validate([
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'comment.required' => 'A reason is required when returning a letter.',
            'comment.min'      => 'Please give the inspector enough detail to act on.',
        ]);

        $this->move($letter, Document::RETURNED, $data['comment']);

        $letter->forceFill(['current_holder_id' => $letter->created_by])->save();

        return redirect()->route('letter.show', $letter)
            ->with('status', 'Letter returned to ' . ($letter->creator?->name ?? 'the drafter') . ' for revision.');
    }

    /** Mark as served on the owner. */
    public function issue(Request $request, Document $letter)
    {
        $this->authorise($letter, Document::ISSUED);

        $this->move($letter, Document::ISSUED, $request->input('comment'), false);
        $letter->forceFill(['issued_at' => now()])->save();

        return redirect()->route('letter.show', $letter)
            ->with('status', 'Letter recorded as issued.');
    }

    /* ============================================================== */

    /** The stage this letter would move to next. */
    private function nextStage(Document $letter): ?string
    {
        return match ($letter->status) {
            Document::DRAFT, Document::RETURNED => Document::PENDING_DIRECTOR,
            Document::PENDING_DIRECTOR          => Document::PENDING_SENIOR,
            Document::PENDING_SENIOR            => Document::PENDING_CHIEF,
            Document::PENDING_CHIEF             => Document::APPROVED,
            Document::APPROVED                  => Document::ISSUED,
            default                             => null,
        };
    }

    /** May the signed-in user move this letter forward right now? */
    private function canAct(Document $letter): bool
    {
        $next = $this->nextStage($letter);

        return $next !== null && auth()->user()->can('transition', [$letter, $next]);
    }

    private function authorise(Document $letter, string $to): void
    {
        abort_unless(auth()->user()->can('transition', [$letter, $to]), 403,
            'You cannot move this letter to that stage.');
    }

    /**
     * Record the move. The transition row is written first so that the
     * trail exists even if a later step fails.
     */
    private function move(Document $letter, string $to, ?string $comment, bool $save = true): void
    {
        $user = auth()->user();
        $from = $letter->status;

        DocumentTransition::create([
            'document_id' => $letter->id,
            'from_status' => $from,
            'to_status'   => $to,
            'actor_id'    => $user->id,
            'actor_role'  => $user->roles->pluck('name')->first() ?? '—',
            'comment'     => $comment,
            'ip_address'  => request()->ip(),
        ]);

        $letter->status = $to;

        if ($to === Document::PENDING_DIRECTOR && ! $letter->submitted_at) {
            $letter->submitted_at = now();
        }

        $letter->current_holder_id = $this->holderFor($letter, $to);

        if ($save) {
            $letter->save();
        }
    }

    /** Whose desk the letter lands on. */
    private function holderFor(Document $letter, string $status): ?int
    {
        $role = match ($status) {
            Document::PENDING_DIRECTOR => 'Director of Inspection',
            Document::PENDING_SENIOR   => 'Senior Inspector',
            Document::PENDING_CHIEF    => 'Chief Inspector',
            Document::APPROVED         => 'Secretary',
            default                    => null,
        };

        if ($role === null) {
            return $letter->created_by;
        }

        $query = \App\Models\User::role($role)->where('is_active', true);

        if ($role === 'Director of Inspection') {
            $query->where('district_id', $letter->district_id);
        }

        return $query->value('id');
    }
}
