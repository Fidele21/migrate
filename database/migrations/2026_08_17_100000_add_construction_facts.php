<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The facts recorded at a construction inspection.
 *
 * These are not judgements — whether the permit is valid and whether the
 * works match the drawings are scored on the checklist. These are the
 * particulars an officer writes down: who is building, under which
 * permit, and how far the work has got.
 *
 * They belong to the visit rather than to the premises, because they
 * change between visits: a contractor is replaced, a permit is renewed,
 * a shell becomes a finished floor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            if (! Schema::hasColumn('inspections', 'permit_number')) {
                $table->string('permit_number', 60)->nullable()->after('stage');
            }
            if (! Schema::hasColumn('inspections', 'permit_expiry')) {
                $table->date('permit_expiry')->nullable()->after('permit_number');
            }
            if (! Schema::hasColumn('inspections', 'contractor')) {
                $table->string('contractor')->nullable()->after('permit_expiry');
            }
            if (! Schema::hasColumn('inspections', 'supervisor')) {
                $table->string('supervisor')->nullable()->after('contractor');
            }
            if (! Schema::hasColumn('inspections', 'building_status')) {
                $table->string('building_status', 80)->nullable()->after('supervisor');
            }
            if (! Schema::hasColumn('inspections', 'dwelling_unit')) {
                $table->string('dwelling_unit', 80)->nullable()->after('building_status');
            }
            if (! Schema::hasColumn('inspections', 'has_physical_plan')) {
                $table->boolean('has_physical_plan')->nullable()->after('dwelling_unit');
            }
            if (! Schema::hasColumn('inspections', 'building_category')) {
                $table->string('building_category', 80)->nullable()->after('has_physical_plan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn([
                'permit_number', 'permit_expiry', 'contractor', 'supervisor',
                'building_status', 'dwelling_unit', 'has_physical_plan',
                'building_category',
            ]);
        });
    }
};