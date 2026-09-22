<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting for a deleted inspection.
 *
 * An inspection can be recorded in error — a test entry, the wrong
 * premises, the same visit entered twice. Removing it should be
 * possible, but not silent: the record should say who removed it and
 * why, so a gap in the sequence can always be explained.
 *
 * The deletion itself remains soft, so nothing is truly lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            if (! Schema::hasColumn('inspections', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            }
            if (! Schema::hasColumn('inspections', 'deleted_reason')) {
                $table->string('deleted_reason', 300)->nullable()->after('deleted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn(['deleted_by', 'deleted_reason']);
        });
    }
};
