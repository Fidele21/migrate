<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Premises and the inspections carried out on them.
 *
 * Entities are deduplicated on UPI, then on name within a district, so
 * a repeat visit attaches to the existing premises rather than creating
 * a second copy of it. That was the defect that made inspection history
 * impossible in the previous platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('type_code', 40);
            $table->string('name', 200);
            $table->string('upi', 60)->nullable();
            $table->string('owner', 160)->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('use_type', 120)->nullable();
            $table->string('zoning', 120)->nullable();

            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('district', 60)->nullable();
            $table->string('sector', 60)->nullable();
            $table->string('cell', 60)->nullable();
            $table->string('village', 60)->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->unsignedInteger('legacy_id')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['type_code', 'district']);
            $table->index('upi');
            $table->index('name');
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('checklist_templates');

            $table->string('type_code', 40);
            $table->date('inspection_date');

            // 'new' or 'followup'. A follow-up carries the previous
            // inspection forward so progress can be compared.
            $table->string('visit_type', 20)->default('new');
            $table->foreignId('previous_inspection_id')->nullable()
                  ->constrained('inspections')->nullOnDelete();
            $table->unsignedInteger('visit_number')->default(1);

            $table->string('status', 20)->default('draft');   // draft | completed

            $table->foreignId('inspector_id')->constrained('users');
            $table->string('inspector_name', 120);
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();

            $table->text('observations')->nullable();
            $table->text('recommendations')->nullable();
            $table->text('owner_recommendations')->nullable();
            $table->string('owner_rep_name', 120)->nullable();

            // Computed at save so history is not rewritten by later
            // changes to a checklist template.
            $table->decimal('earned_score', 7, 2)->default(0);
            $table->decimal('possible_score', 7, 2)->default(0);
            $table->decimal('compliance', 5, 2)->default(0);

            $table->unsignedInteger('legacy_id')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['type_code', 'inspection_date']);
            $table->index(['entity_id', 'inspection_date']);
            $table->index('status');
        });

        Schema::create('inspection_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('checklist_items');
            $table->string('status', 10)->nullable();   // yes | no | na
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['inspection_id', 'item_id']);
            $table->index('status');
        });

        Schema::create('inspection_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('institution', 160)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_team');
        Schema::dropIfExists('inspection_answers');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('entities');
    }
};
