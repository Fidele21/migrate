<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Inspection;

/**
 * My Box — a person's own workspace.
 *
 * Everything on this desk, in one place: inspections still in draft,
 * reports and letters awaiting this person's action, work they have
 * sent onward, and what has come back for revision.
 *
 * What appears depends on the permissions held, so an Inspector sees
 * their drafts and returned work while a Chief Inspector sees documents
 * awaiting signature.
 */
class MyBoxController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $has  = fn (string $p) => $user->can($p);

        /* ---- Inspections ---- */
        $myDrafts = Inspection::with('entity')
            ->where('inspector_id', $user->id)
            ->where('status', 'draft')
            ->orderByDesc('updated_at')->get();

        $myCompleted = Inspection::with('entity')
            ->where('inspector_id', $user->id)
            ->where('status', 'completed')
            ->orderByDesc('inspection_date')->limit(20)->get();

        /* ---- Documents, where the table exists ---- */
        $awaiting = collect();
        $returned = collect();
        $sentOn   = collect();
        $signed   = collect();

        if (class_exists(Document::class) && \Schema::hasTable('documents')) {

            $scoped = fn ($q) => $user->isCityWide()
                ? $q
                : $q->where('district_id', $user->district_id);

            $statuses = [];
            if ($has('document.verify.district')) $statuses[] = Document::PENDING_DIRECTOR;
            if ($has('document.verify.city'))     $statuses[] = Document::PENDING_SENIOR;
            if ($has('document.approve'))         $statuses[] = Document::PENDING_CHIEF;
            if ($has('letter.reference'))         $statuses[] = Document::APPROVED;

            /* The Mayors approve closure notices and nothing else, so only
               those reach their desk for signature. Restricted to the
               approval step alone — whatever else they are entitled to see
               waiting on them arrives unfiltered, as it does for anyone. */
            $closureOnly = ! $has('document.approve') && $has('document.approve.closure');

            if ($statuses || $closureOnly) {
                $awaiting = $scoped(Document::with('creator')->where(
                    function ($q) use ($statuses, $closureOnly) {
                        if ($statuses) {
                            $q->whereIn('status', $statuses);
                        }

                        if ($closureOnly) {
                            $q->orWhere(fn ($c) => $c
                                ->where('status', Document::PENDING_CHIEF)
                                ->where('type', 'letter')
                                ->whereIn('letter_type', Document::CLOSURE_LETTERS));
                        }
                    }
                ))->orderBy('submitted_at')->get();
            }

            $returned = Document::where('created_by', $user->id)
                ->whereIn('status', [Document::RETURNED, Document::DRAFT])
                ->orderByDesc('updated_at')->get();

            $sentOn = Document::where('created_by', $user->id)
                ->whereIn('status', [
                    Document::PENDING_DIRECTOR, Document::PENDING_SENIOR, Document::PENDING_CHIEF,
                ])->orderByDesc('submitted_at')->get();

            $signed = Document::whereHas('signatures', fn ($q) => $q->where('signer_id', $user->id))
                ->orderByDesc('approved_at')->limit(15)->get();
        }

        /* Reports open for signature that this person still owes.
           Filtered in PHP rather than SQL because whether the Director
           may sign depends on every officer having signed first, which
           is a rule rather than a column. */
        $toSign = Document::where('type', 'report')
            ->where('signature_stage', 'pending_signatures')
            ->whereNull('sealed_at')
            ->with('inspection.entity', 'inspection.team', 'signatures')
            ->get()
            ->filter(fn ($d) => $d->awaitingSignatureFrom($user))
            ->values();

        /* Work given to this person by someone else, still wanting
           inspections. Guarded because the assignments table arrived
           later than this controller: an installation that has not run
           that migration yet should show a quiet zero rather than an
           error on the page every officer opens first. */
        $assignmentCount = \Schema::hasTable('assignments')
            ? AssignmentController::openCountFor($user)
            : 0;

        return view('mybox', [
            'myDrafts'        => $myDrafts,
            'myCompleted'     => $myCompleted,
            'awaiting'        => $awaiting,
            'returned'        => $returned,
            'sentOn'          => $sentOn,
            'signed'          => $signed,
            'toSign'          => $toSign,
            'assignmentCount' => $assignmentCount,
            'role'            => $user->roles->pluck('name')->first() ?? 'Inspector',
            'canReview'       => $has('document.verify.district') || $has('document.verify.city')
                                 || $has('document.approve') || $has('letter.reference'),
        ]);
    }
}