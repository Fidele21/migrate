<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields specific to enforcement correspondence.
 *
 * The reference number is assigned by the Secretary once the Chief
 * Inspector has signed, not at drafting — so a draft that is never
 * issued does not consume a number from the registry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('letter_type', 40)->nullable()->after('type');
            $table->string('subject', 255)->nullable()->after('title');
            $table->string('salutation', 20)->default('company')->after('subject');

            $table->unsignedInteger('deadline_days')->nullable()->after('salutation');
            $table->date('deadline_date')->nullable()->after('deadline_days');

            // Where this letter answers or follows earlier correspondence.
            $table->string('prior_reference', 60)->nullable()->after('deadline_date');
            $table->date('prior_date')->nullable()->after('prior_reference');
            $table->date('reply_date')->nullable()->after('prior_date');

            // Secretary's handling.
            $table->unsignedInteger('serial')->nullable()->after('reference_number');
            $table->timestamp('referenced_at')->nullable()->after('serial');
            $table->foreignId('referenced_by')->nullable()->after('referenced_at')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('printed_at')->nullable()->after('referenced_by');
            $table->string('scan_path')->nullable()->after('printed_at');
            $table->timestamp('dispatched_at')->nullable()->after('scan_path');
            $table->string('dispatched_to', 180)->nullable()->after('dispatched_at');

            $table->index('letter_type');
            $table->index('serial');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['referenced_by']);
            $table->dropColumn([
                'letter_type', 'subject', 'salutation', 'deadline_days', 'deadline_date',
                'prior_reference', 'prior_date', 'reply_date', 'serial',
                'referenced_at', 'referenced_by', 'printed_at', 'scan_path',
                'dispatched_at', 'dispatched_to',
            ]);
        });
    }
};
