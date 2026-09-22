<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two checklists for one category.
 *
 * An ongoing construction site is inspected differently depending on how
 * far it has got. Substructure work — trenches, rebar, blinding, concrete
 * cover — is finished and buried before superstructure begins, so the two
 * cannot be assessed on one visit and should not share one checklist.
 *
 * The category stays as Ongoing Construction; the inspector chooses the
 * stage when starting, and the platform serves the matching checklist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('checklist_templates', 'stage')) {
                $table->string('stage', 32)->nullable()->after('type_code');
            }
        });

        Schema::table('inspections', function (Blueprint $table) {
            if (! Schema::hasColumn('inspections', 'stage')) {
                $table->string('stage', 32)->nullable()->after('type_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('checklist_templates', fn (Blueprint $t) => $t->dropColumn('stage'));
        Schema::table('inspections', fn (Blueprint $t) => $t->dropColumn('stage'));
    }
};
