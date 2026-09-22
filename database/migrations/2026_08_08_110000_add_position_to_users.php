<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The position a person holds, as it appears beneath their signature.
 *
 * Distinct from their role, which governs what they may do. Two officers
 * may both be Inspectors in the system while holding different posts —
 * an Electrical and Mechanical Inspector and a Building Inspector.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'position')) {
                $table->string('position', 120)->nullable()->after('employee_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
