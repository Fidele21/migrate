<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TIN — not part of the original paper form, added on top of it.
 *
 * Held on the wastewater record itself rather than the shared entities
 * table: if the same taxpayer identifier should be recorded against
 * every premises regardless of category, that is a different, larger
 * change, and one the platform should make deliberately rather than as
 * a side effect of one checklist's own form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wastewater_inspections', function (Blueprint $table) {
            $table->string('tin_number', 20)->nullable()->after('inspection_id');
        });
    }

    public function down(): void
    {
        Schema::table('wastewater_inspections', function (Blueprint $table) {
            $table->dropColumn('tin_number');
        });
    }
};