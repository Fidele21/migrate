<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signing a report.
 *
 * A report is the record of what officers found on site, so the people
 * who were there sign it first. The Director of Inspection signs last,
 * and only once every officer on the team has — a supervisor should not
 * certify findings the officers themselves have not yet stood behind.
 *
 * Once the Director signs, the report is sealed. It can be downloaded
 * and transmitted, but not altered: a signature that does not fix the
 * content is not a signature.
 *
 * document_signatures already records the signer, their role, the
 * content hash, the method and the address it came from. Only two things
 * are missing: which stage of signing this is, and any remark the signer
 * wished to attach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'signature_stage')) {
                $table->string('signature_stage', 32)->nullable()->after('status');
            }
            if (! Schema::hasColumn('documents', 'signatures_opened_at')) {
                $table->timestamp('signatures_opened_at')->nullable();
            }
            if (! Schema::hasColumn('documents', 'sealed_at')) {
                $table->timestamp('sealed_at')->nullable();
            }
            if (! Schema::hasColumn('documents', 'sealed_hash')) {
                $table->string('sealed_hash', 64)->nullable();
            }
        });

        Schema::table('document_signatures', function (Blueprint $table) {
            if (! Schema::hasColumn('document_signatures', 'stage')) {
                // 'inspector' or 'director'
                $table->string('stage', 24)->default('inspector')->after('signer_role');
            }
            if (! Schema::hasColumn('document_signatures', 'remark')) {
                $table->text('remark')->nullable()->after('stage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['signature_stage', 'signatures_opened_at', 'sealed_at', 'sealed_hash']);
        });

        Schema::table('document_signatures', function (Blueprint $table) {
            $table->dropColumn(['stage', 'remark']);
        });
    }
};