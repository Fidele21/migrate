<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ties each team member to their user account.
 *
 * A report or letter may only be drafted and edited by an officer who
 * conducted the inspection. That check needs the team recorded as
 * accounts rather than as typed names, so the platform can tell who was
 * actually present.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_team', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('inspection_id')
                  ->constrained('users')->nullOnDelete();
            $table->index('user_id');
        });

        // Match existing team members to accounts by name.
        foreach (DB::table('inspection_team')->whereNull('user_id')->get() as $m) {
            $user = DB::table('users')
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($m->name))])
                ->first();

            if ($user) {
                DB::table('inspection_team')->where('id', $m->id)->update(['user_id' => $user->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('inspection_team', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
