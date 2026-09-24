<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Carries a temporary password to its holder.
 *
 * Used both when an account is opened and when an administrator resets a
 * password. The password inside is deliberately short-lived: the account
 * carries must_change_password, so EnsurePasswordChanged forces it to be
 * replaced before the holder can reach anything, and it is worthless once
 * that has happened.
 */
class AccountCredentials extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
        public bool $isReset = false,
        public ?string $issuedBy = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isReset
                ? 'City of Kigali — your password has been reset'
                : 'City of Kigali — your Inspection Platform account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.credentials',
            with: [
                'signInUrl' => route('login'),
                'roleName'  => $this->user->roles->pluck('name')->first(),
            ],
        );
    }
}
