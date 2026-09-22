<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a desk review records.
 *
 * A review is of an application, not of a place. It has an application
 * number, and where a permit has already issued, the number and date of
 * that permit. The date of the review is its own — an inspection_date on
 * a desk review would mean the day the file was read, which is a
 * different thing from the day someone stood on the plot.
 *
 * permit_number already exists, added for construction, and means the
 * same thing here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            if (! Schema::hasColumn('inspections', 'application_number')) {
                $table->string('application_number', 60)->nullable()->after('permit_number');
            }
            if (! Schema::hasColumn('inspections', 'permit_issued_on')) {
                $table->date('permit_issued_on')->nullable()->after('application_number');
            }
            if (! Schema::hasColumn('inspections', 'reviewed_on')) {
                $table->date('reviewed_on')->nullable()->after('permit_issued_on');
            }

            $table->index('application_number');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropIndex(['application_number']);
            $table->dropColumn(['application_number', 'permit_issued_on', 'reviewed_on']);
        });
    }
};