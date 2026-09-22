<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wastewater Management Inspection has no checklist template — nothing
 * here is scored, so there is genuinely nothing for template_id to
 * reference. Every category before this one had a real checklist, so
 * the column was never allowed to be null; wastewater is the first
 * inspection type without one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable(false)->change();
        });
    }
};