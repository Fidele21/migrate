<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * The single place that decides who may act on a document.
 *
 * Every check combines three questions:
 *   1. Is this transition legal from the document's current status?
 *   2. Does the user hold the permission that transition requires?
 *   3. Does the user's district cover the document's district?
 *
 * All three must pass. In the current live platform these questions
 * are scattered across many files and answered inconsistently, which
 * is how a Director came to outrank the Chief Inspector.
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if (! $user->is_active) {
            return false;
        }
        if ($user->can('inspection.view.all')) {
            return true;
        }
        if (! $user->coversDistrict($document->district_id)) {
            return false;
        }
        if ($user->can('inspection.view.district')) {
            return true;
        }
        return $document->created_by === $user->id;
    }

    public function update(User $user, Document $document): bool
    {
        return $document->isEditable()
            && $user->can('document.edit')
            && $user->coversDistrict($document->district_id)
            && $document->created_by === $user->id;
    }

    /**
     * The core check. Legal transition + required permission + district scope.
     */
    public function transition(User $user, Document $document, string $toStatus): bool
    {
        if (! $user->is_active || ($document->isFrozen() && $toStatus !== Document::ISSUED)) {
            return false;
        }

        $permission = $document->permissionFor($toStatus);
        if ($permission === null) {
            return false;   // not a legal move from here
        }

        $held = $user->can($permission)
            || $this->mayApproveThisClosure($user, $document, $permission);

        return $held && $user->coversDistrict($document->district_id);
    }

    /**
     * The Mayors' narrow approval.
     *
     * Closing a premises is a decision the city carries politically, so the
     * Lord Mayor and the Vice Mayor sign off a closure notice themselves.
     * It buys them nothing anywhere else: the permission is additive and
     * reaches only the approval step of a closure letter, which is why it
     * is checked here rather than widening document.approve.
     */
    private function mayApproveThisClosure(User $user, Document $document, string $permission): bool
    {
        return $permission === 'document.approve'
            && $document->isClosureLetter()
            && $user->can('document.approve.closure');
    }

    public function submit(User $user, Document $document): bool
    {
        return $this->transition($user, $document, Document::PENDING_DIRECTOR);
    }

    public function verifyAsDirector(User $user, Document $document): bool
    {
        return $this->transition($user, $document, Document::PENDING_SENIOR);
    }

    public function verifyAsSenior(User $user, Document $document): bool
    {
        return $this->transition($user, $document, Document::PENDING_CHIEF);
    }

    public function approve(User $user, Document $document): bool
    {
        return $this->transition($user, $document, Document::APPROVED);
    }

    public function issue(User $user, Document $document): bool
    {
        return $this->transition($user, $document, Document::ISSUED);
    }

    public function returnForRevision(User $user, Document $document): bool
    {
        return $this->transition($user, $document, Document::RETURNED);
    }

    /** Nothing is ever hard deleted. Archiving requires explicit permission. */
    public function delete(User $user, Document $document): bool
    {
        return false;
    }
}
