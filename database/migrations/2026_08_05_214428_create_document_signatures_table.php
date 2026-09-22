<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signatures applied to documents.
 *
 * Immutable. Each row binds a signer to the exact content they signed,
 * via a SHA-256 hash of the document body at that moment. If the
 * document is later altered, the hash no longer matches and the
 * tampering is detectable.
 *
 * Without this a signature is only a claim that somebody clicked a
 * button. With it, you can prove what was signed and by whom - which
 * is what an enforcement notice needs if it is ever contested.
 *
 * Rwanda recognises electronic signatures under Law No. 18/2010.
 * Confirm with CoK legal which standard applies before the first
 * letter is issued electronically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();

            /* Required. A signature pointing at no document proves
               nothing, so the database refuses to hold one.

               restrictOnDelete rather than cascade: a signed document
               must not be deletable while its signatures exist. With
               soft deletes this never fires; with a hard delete it is
               the difference between an audit trail and a gap in one. */
            $table->foreignId('document_id')
                  ->constrained()
                  ->restrictOnDelete();

            /* Nullable, and nulled if the account is removed. The record
               rests on signer_name and signer_role below, not on this
               link - so a departing officer does not erase their own
               signature, and does not become undeletable either. */
            $table->foreignId('signer_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // The role held at the moment of signing. Stored rather than
            // derived, because a person's role may change afterwards.
            $table->string('signer_role', 60);
            $table->string('signer_name', 120);

            // SHA-256 of the document body as it stood when signed.
            $table->string('content_hash', 64);

            $table->string('signature_method', 20)->default('drawn');
            $table->string('signature_path')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('signed_at')->useCurrent();

            $table->index(['document_id', 'signed_at']);
            $table->index('signer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
    }
};