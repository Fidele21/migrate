<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wastewater — the fields that sit alongside the checklist, not inside
 * it. Same pattern as add_construction_facts: nothing here is scored,
 * these are recorded once per inspection and read directly, the same
 * way permit_number and building_status already work for construction.
 *
 * Two groups of fields exist only for their matching stage — the STP
 * fields mean nothing on a septic tank inspection, and the reverse.
 * Both live on the same row regardless, exactly as construction's stage
 * already works: the form shows only the fields that apply, the column
 * for the other stage simply stays null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            /* ---- General (every wastewater inspection) ---- */
            $table->string('occupation_permit_number', 60)->nullable();
            $table->unsignedSmallInteger('occupation_permit_year')->nullable();
            $table->string('manager_name', 160)->nullable();
            $table->string('manager_telephone', 40)->nullable();
            $table->string('wastewater_system_type', 30)->nullable();
            // stp | septic_tank | public_sewer | soak_pit | none | other
            $table->string('wastewater_system_type_other', 120)->nullable();

            /* ---- STP — descriptive, not scored ---- */
            $table->string('stp_type', 40)->nullable();
            $table->string('stp_type_other', 120)->nullable();
            $table->decimal('design_capacity_m3', 10, 2)->nullable();
            $table->unsignedInteger('population_served')->nullable();
            $table->decimal('water_consumption_m3', 10, 2)->nullable();
            $table->unsignedSmallInteger('year_constructed')->nullable();
            $table->date('last_maintenance_date')->nullable();
            $table->date('last_desludging_date')->nullable();
            $table->string('desludging_frequency', 30)->nullable();
            // the recorded value — the checklist item only asks whether
            // this was recorded at all, not which frequency
            $table->string('operational_status', 30)->nullable();
            // functional | partially_functional | non_functional
            $table->text('stp_general_condition')->nullable();
            $table->date('effluent_test_date')->nullable();
            $table->string('laboratory_name', 255)->nullable();
            $table->string('final_discharge_point', 40)->nullable();

            /* ---- Septic tank — descriptive, not scored ---- */
            $table->string('septic_tank_type', 30)->nullable();
            $table->unsignedTinyInteger('septic_chamber_count')->nullable();
            $table->string('septic_tank_material', 30)->nullable();
            $table->decimal('septic_tank_capacity', 10, 2)->nullable();
            $table->unsignedSmallInteger('septic_year_installed')->nullable();
            $table->decimal('distance_to_foundation_m', 6, 2)->nullable();
            $table->string('septic_tank_location', 30)->nullable();
            $table->string('absorption_field_condition', 20)->nullable();
            $table->string('effluent_disposal_method', 30)->nullable();
            $table->string('desludging_provider', 255)->nullable();
            $table->string('tank_adequacy', 20)->nullable();
            $table->text('septic_observations')->nullable();

            /* ---- Findings, common to both stages ---- */
            $table->json('non_compliances')->nullable();
            $table->string('non_compliances_other', 255)->nullable();
            $table->text('key_findings')->nullable();
            $table->text('environmental_risks')->nullable();

            /* ---- Enforcement ---- */
            $table->string('wastewater_compliance_status', 20)->nullable();
            // compliant | non_compliant — two values, not three; this
            // form's own decision, separate from the platform's
            // ComplianceBand percentage bands
            $table->string('decision_taken', 40)->nullable();
            // advisory_notice | improvement_notice | warning | fine_issued
            // temporary_closure | permanent_closure | reinspection_required
            $table->unsignedInteger('wastewater_fine_amount')->nullable();
            $table->date('wastewater_compliance_deadline')->nullable();
            $table->date('wastewater_followup_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn([
                'occupation_permit_number', 'occupation_permit_year',
                'manager_name', 'manager_telephone',
                'wastewater_system_type', 'wastewater_system_type_other',
                'stp_type', 'stp_type_other', 'design_capacity_m3',
                'population_served', 'water_consumption_m3', 'year_constructed',
                'last_maintenance_date', 'last_desludging_date', 'desludging_frequency',
                'operational_status', 'stp_general_condition',
                'effluent_test_date', 'laboratory_name', 'final_discharge_point',
                'septic_tank_type', 'septic_chamber_count', 'septic_tank_material',
                'septic_tank_capacity', 'septic_year_installed', 'distance_to_foundation_m',
                'septic_tank_location', 'absorption_field_condition',
                'effluent_disposal_method', 'desludging_provider', 'tank_adequacy',
                'septic_observations',
                'non_compliances', 'non_compliances_other', 'key_findings', 'environmental_risks',
                'wastewater_compliance_status', 'decision_taken', 'wastewater_fine_amount',
                'wastewater_compliance_deadline', 'wastewater_followup_date',
            ]);
        });
    }
};