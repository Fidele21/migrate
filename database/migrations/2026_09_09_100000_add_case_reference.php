<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One reference across the whole case.
 *
 * When the Secretary assigns a number to the first letter arising from
 * an inspection, that number becomes the case reference. The inspection
 * report and any fines carry it too, so an officer holding a letter can
 * find everything connected to it by typing one number.
 *
 * Later letters on the same case — an extension, a closure notice — take
 * their own registry number but keep the case reference alongside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('case_reference', 60)->nullable()->after('id');
            $table->index('case_reference');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('case_reference', 60)->nullable()->after('reference_number');
            $table->index('case_reference');
        });

        Schema::table('fines', function (Blueprint $table) {
            $table->string('case_reference', 60)->nullable()->after('reference');
            $table->index('case_reference');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropIndex(['case_reference']);
            $table->dropColumn('case_reference');
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['case_reference']);
            $table->dropColumn('case_reference');
        });
        Schema::table('fines', function (Blueprint $table) {
            $table->dropIndex(['case_reference']);
            $table->dropColumn('case_reference');
        });
    }
};
