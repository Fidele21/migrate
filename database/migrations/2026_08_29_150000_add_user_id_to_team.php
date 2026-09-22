<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team members linked to real accounts, not matched by name.
 *
 * The model has listed user_id as fillable for some time — code
 * downstream (the report's signature block) already reads it — but the
 * column itself was never created. Every team member has therefore been
 * saved as free text only, and signature-matching has silently fallen
 * back to comparing names, which is exactly how officers went unlinked
 * before.
 *
 * The table is 'inspection_team', overridden explicitly on the model —
 * not 'inspection_team_members', which Eloquent's naming convention
 * would otherwise guess and which does not exist.
 *
 * Nullable: a team member with no platform account (a colleague from
 * another agency, on a joint inspection) is a normal, expected case, not
 * an error — they simply cannot sign, which the platform already shows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_team', function (Blueprint $table) {
            if (! Schema::hasColumn('inspection_team', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('inspection_id')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inspection_team', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};