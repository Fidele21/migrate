<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Position held by each member of the inspection team.
 *
 * The report is signed by the inspectors who conducted the site visit
 * and by the District Inspection Unit Director, each shown with their
 * position. The letter, by contrast, carries only the Chief Inspector's
 * signature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_team', function (Blueprint $table) {
            $table->string('position', 120)->nullable()->after('name');
            $table->boolean('is_lead')->default(false)->after('institution');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_team', function (Blueprint $table) {
            $table->dropColumn(['position', 'is_lead']);
        });
    }
};
