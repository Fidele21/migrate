<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requirements where the presence of something is the fault.
 *
 * Most checklist items ask whether something required is there — a fire
 * extinguisher, a valid licence — and "yes" means compliant.
 *
 * A few ask the opposite. A kitchen or bar at a petrol station is
 * prohibited: open flame beside fuel storage. For those items "yes"
 * means non-compliant, and scoring them the usual way awards points for
 * the hazard itself.
 *
 * The flag is held on the item rather than inferred from its wording,
 * because a checklist written by one person and read by another cannot
 * rely on phrasing alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->boolean('is_prohibition')->default(false)->after('allows_na');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->dropColumn('is_prohibition');
        });
    }
};
