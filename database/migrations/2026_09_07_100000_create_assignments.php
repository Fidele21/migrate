<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inspection assignments — work given out, not work recorded.
 *
 * An assignment is a target: so many inspections of one category, by a
 * given date, by named officers. It is not an inspection record and
 * holds no findings; the inspections that satisfy it are ordinary
 * records the officers create as they always have, counted against the
 * target by category, officer and date.
 *
 * Where several officers hold one assignment they work it as a team.
 * The quantity is the team's, not each member's, and progress is what
 * the team has done between them. Every member is credited with the
 * team's rate: eight of ten is eighty per cent for each of them, not
 * eighty divided by however many were on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();

            $table->string('type_code', 40);
            $table->unsignedSmallInteger('quantity');
            $table->date('starts_on');
            $table->date('due_on');
            $table->text('instructions')->nullable();

            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamp('assigned_at')->useCurrent();

            /* The district the work sits in. A Director assigns within
               their own; a city-wide officer carries the district of
               whoever they assigned to. */
            $table->foreignId('district_id')->nullable()
                  ->constrained('districts')->nullOnDelete();

            $table->string('status', 20)->default('active');
            // active | completed | cancelled

            $table->timestamps();
            $table->softDeletes();

            $table->index(['type_code', 'status']);
            $table->index('due_on');
        });

        /* Who holds the assignment. Several officers on one row would
           mean parsing a list to answer "what is mine" — the question
           this table exists to answer. */
        Schema::create('assignment_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['assignment_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_members');
        Schema::dropIfExists('assignments');
    }
};
