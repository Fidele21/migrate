<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recording that a fine was adjusted.
 *
 * Without keeping what the schedule originally set, an adjustment is
 * invisible: the fine simply reads as a different figure from the one its
 * faults produce, and nobody looking at it later can tell whether it was
 * reduced or recorded wrongly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fines', function (Blueprint $table) {
            if (! Schema::hasColumn('fines', 'original_amount')) {
                $table->decimal('original_amount', 12, 2)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('fines', 'adjusted_by')) {
                $table->foreignId('adjusted_by')->nullable()->after('confirm_note')
                    ->constrained('users');
            }
            if (! Schema::hasColumn('fines', 'adjusted_at')) {
                $table->timestamp('adjusted_at')->nullable()->after('adjusted_by');
            }
            if (! Schema::hasColumn('fines', 'adjust_reason')) {
                $table->text('adjust_reason')->nullable()->after('adjusted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjusted_by');
            $table->dropColumn(['original_amount', 'adjusted_at', 'adjust_reason']);
        });
    }
};