<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned checklists.
 *
 * A published template is immutable. Changing a weight or wording
 * creates version 2; inspections stay bound to the version they were
 * carried out under, so a report always reproduces exactly what was
 * assessed on the day. In the previous platform, editing an item
 * silently rewrote the score of every past inspection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type_code', 40);          // petrol, building, ...
            $table->unsignedInteger('version')->default(1);
            $table->string('name');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['type_code', 'version']);
            $table->index('type_code');
        });

        Schema::create('checklist_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->string('section_number', 10);
            $table->string('title', 200);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['template_id', 'sort_order']);
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('checklist_sections')->cascadeOnDelete();
            $table->string('item_code', 60);
            $table->text('label');
            $table->decimal('max_score', 6, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('allows_na')->default(true);
            $table->timestamps();

            $table->index(['section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklist_sections');
        Schema::dropIfExists('checklist_templates');
    }
};
