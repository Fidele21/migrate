<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A version is unique within a category and stage, not within a category.
 *
 * Ongoing construction publishes two checklists — substructure and
 * superstructure — and both begin at version 1. The old index treated
 * that as a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_templates', function (Blueprint $table) {
            $table->dropUnique('checklist_templates_type_code_version_unique');
            $table->unique(['type_code', 'stage', 'version'], 'checklist_templates_type_stage_version_unique');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_templates', function (Blueprint $table) {
            $table->dropUnique('checklist_templates_type_stage_version_unique');
            $table->unique(['type_code', 'version'], 'checklist_templates_type_code_version_unique');
        });
    }
};
