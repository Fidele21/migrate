<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delegation: an assignment may be passed down, a share at a time.
 *
 * A Chief Inspector gives thirty petrol stations to three Directors.
 * That is not one target of thirty worked between them — each Director
 * owns ten, in their own district, and answers for those ten. A Director
 * may then pass their ten to the inspectors of their district, all at
 * once or in parts: four to one team, six to another, or six out and
 * four kept for themselves.
 *
 * So an assignment needs a parent, and a quantity that means "my share"
 * rather than "the whole job". The share is split evenly when it is
 * handed to several officers of the same rank, because a rank is a rank;
 * where the work should not be split evenly, the officer giving it out
 * makes two assignments rather than one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            /* The assignment this one was carved out of. Null for work
               that originates with the officer giving it — a Chief
               Inspector's own decision to inspect thirty stations. */
            $table->foreignId('parent_id')->nullable()->after('id')
                  ->constrained('assignments')->cascadeOnDelete();

            /* How the holders work it. Inspectors on one assignment work
               as a team against a shared target; officers of senior rank
               each hold their own share and answer for it alone. */
            $table->string('sharing', 12)->default('team')->after('quantity');
            // team | individual

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'sharing']);
        });
    }
};