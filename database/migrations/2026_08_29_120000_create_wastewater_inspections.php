<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wastewater Management Inspection.
 *
 * Not a scored checklist. The form ends in a judgement the inspector
 * makes directly — Compliant, Partially Compliant, or Non-Compliant —
 * not a percentage computed from weighted items. Nothing here carries a
 * max_score, and there is no compliance figure to put on a dashboard
 * beside the checklist categories.
 *
 * The system found on site decides which further section applies. A
 * sewage treatment plant and a septic tank are different pieces of
 * infrastructure with different failure modes; asking about chlorination
 * dosing on a septic tank, or chamber count on a treatment plant, would
 * ask questions that do not apply to what is actually there. Section 4
 * (septic tank) and Section 5 (treatment plant) are therefore mutually
 * exclusive, chosen by system_type, and only one is ever filled in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wastewater_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->unique()->constrained()->cascadeOnDelete();

            /* ---- Section 1: Inspection Information ---- */
            $table->string('village', 120)->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->string('inspection_type', 40)->nullable();
            // routine | follow_up | complaint | joint | emergency

            /* ---- Section 2: Facility Information ---- */
            $table->string('facility_name', 255)->nullable();
            $table->string('building_use', 60)->nullable();
            // hotel | apartment | commercial | hospital | health_centre | school
            // university | market | restaurant | industry | office | church
            // residential | mixed_use | other
            $table->string('building_use_other', 120)->nullable();

            /* ---- Section 3: Wastewater Management System ---- */
            $table->string('system_type', 40)->nullable();
            // stp | septic_tank | public_sewer | soak_pit | bio_digester | none | other
            $table->string('system_type_other', 120)->nullable();
            $table->decimal('design_capacity_m3', 10, 2)->nullable();
            $table->unsignedInteger('population_served')->nullable();
            $table->decimal('water_consumption_m3', 10, 2)->nullable();
            $table->unsignedSmallInteger('year_constructed')->nullable();
            $table->string('maintenance_company', 255)->nullable();
            $table->date('last_maintenance_date')->nullable();
            $table->date('last_desludging_date')->nullable();
            $table->string('desludging_frequency', 30)->nullable();
            // monthly | quarterly | every_6_months | annually | as_needed | never

            /* ---- Section 4: Septic Tank System Details ----
               Filled only when system_type = septic_tank. */
            $table->string('septic_tank_type', 30)->nullable();
            // single_chamber | two_chamber | three_chamber | prefabricated | unknown
            $table->unsignedTinyInteger('septic_chamber_count')->nullable();
            $table->string('septic_tank_material', 30)->nullable();
            // concrete | masonry | plastic_hdpe | fiberglass | other
            $table->string('septic_tank_material_other', 120)->nullable();
            $table->string('septic_tank_location', 30)->nullable();
            // inside_compound | outside_compound | under_building | other
            $table->string('septic_tank_location_other', 120)->nullable();

            /* Component condition grid: inlet pipe, outlet/baffle, access
               cover, vent pipe, scum layer, sludge layer — each rated
               good/fair/poor/not_available. Stored as one JSON object
               rather than six columns, because the component list is
               fixed by the paper form and does not vary per inspection;
               splitting it into six near-identical columns would add
               nothing a form field could not equally express. */
            $table->json('septic_components')->nullable();

            $table->boolean('has_absorption_field')->nullable();
            $table->string('absorption_field_condition', 20)->nullable();
            // good | fair | poor | not_applicable
            $table->string('effluent_disposal_method', 30)->nullable();
            // soak_pit | direct_discharge | wetland | open_ground | reuse | unknown | emptying

            $table->boolean('signs_of_leakage')->nullable();
            $table->boolean('signs_of_overflow')->nullable();
            $table->boolean('structural_damage_visible')->nullable();
            $table->boolean('odour_detected')->nullable();

            $table->string('desludging_provider', 255)->nullable();
            $table->string('tank_adequacy', 20)->nullable();
            // adequate | undersized | unable_to_determine
            $table->text('septic_observations')->nullable();

            /* ---- Section 5: Operational Status ----
               Filled only when system_type = stp (sewage treatment plant). */
            $table->string('operational_status', 20)->nullable();
            // functional | partially_functional | non_functional

            /* screening chamber, pumps, air blowers, chlorination, oil &
               grease trap, sludge drying bed, flow meter, ventilation —
               same reasoning as septic_components above. */
            $table->json('stp_components')->nullable();

            $table->text('general_condition')->nullable();

            /* ---- Section 6: Environmental Compliance ---- */
            $table->boolean('effluent_test_available')->nullable();
            $table->date('effluent_test_date')->nullable();
            $table->string('laboratory_name', 255)->nullable();
            $table->boolean('effluent_test_valid')->nullable();
            $table->string('effluent_meets_standards', 20)->nullable();
            // yes | no | not_tested

            $table->string('final_discharge_point', 30)->nullable();
            // public_sewer | public_drainage | wetland | river | soak_pit | reuse | other
            $table->string('final_discharge_point_other', 120)->nullable();

            $table->boolean('untreated_discharge_evidence')->nullable();
            $table->boolean('pollution_evidence')->nullable();

            /* Multi-select: bad odour, overflow, high turbidity, foam,
               solid waste, oil & grease, dead vegetation, wetland
               pollution, river pollution, none. Stored as a JSON array
               of the options actually ticked. */
            $table->json('pollution_types')->nullable();

            $table->text('key_findings')->nullable();
            $table->text('environmental_risks')->nullable();

            /* ---- Section 7: Inspection Findings ----
               Multi-select: no STP, STP not functional, poor maintenance,
               pumps not working, air blower faulty, chlorination not
               working, overflow, illegal discharge, no effluent test
               results, no maintenance records, capacity insufficient,
               uncovered chambers, poor sludge management, other. */
            $table->json('non_compliances')->nullable();
            $table->string('non_compliances_other', 255)->nullable();

            /* ---- Section 8: Enforcement Actions ----
               The judgement. Not computed — the inspector's own
               conclusion from everything recorded above. */
            $table->string('compliance_status', 20)->nullable();
            // compliant | partially_compliant | non_compliant

            $table->text('corrective_actions')->nullable();
            $table->date('compliance_deadline')->nullable();
            $table->date('followup_inspection_date')->nullable();
            $table->text('additional_remarks')->nullable();

            $table->string('facility_representative_name', 255)->nullable();
            $table->text('facility_representative_comments')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastewater_inspections');
    }
};