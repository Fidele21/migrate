<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reports and enforcement letters.
 *
 * Both travel the same approval chain but move independently: a report
 * may be approved while its letter is still under revision.
 *
 * Once a document reaches 'approved' it is frozen. Corrections create a
 * new version linked by supersedes_id; nothing is ever overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            $table->string('type', 20);              // report | letter
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()
                  ->constrained('documents')->nullOnDelete();

            // Which inspection this concerns. The inspections table is not
            // built yet, so this is unconstrained for now.
            $table->unsignedBigInteger('inspection_id')->nullable()->index();

            $table->string('status', 30)->default('draft');
            $table->foreignId('current_holder_id')->nullable()
                  ->constrained('users')->nullOnDelete();

            // Copied from the entity at creation. Drives district scoping,
            // and must not shift if the entity is later edited.
            $table->foreignId('district_id')->nullable()
                  ->constrained('districts')->nullOnDelete();

            $table->string('reference_number', 60)->nullable()->unique();
            $table->string('title');
            $table->longText('body_html')->nullable();

            $table->foreignId('created_by')->constrained('users');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();

            // SHA-256 of the body at approval. Any later alteration breaks
            // the hash, making tampering detectable.
            $table->string('content_hash', 64)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'district_id']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
