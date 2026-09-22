<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The detail record for a Wastewater Management Inspection.
 *
 * One row per inspection, holding fields the shared inspections table
 * has no place for — GPS, tank capacity, component condition grids, the
 * inspector's final judgement. There is no compliance percentage: the
 * form ends in compliance_status, a judgement the inspector makes
 * directly from what was observed, not a score computed from weighted
 * items.
 *
 * Section 4 (septic tank) and Section 5 (treatment plant) are mutually
 * exclusive. Which one applies is decided by system_type, not by the
 * officer choosing a stage the way construction inspections do — the
 * infrastructure on site is simply one or the other.
 */
class WastewaterInspection extends Model
{
    use HasFactory;

    public const INSPECTION_TYPES = [
        'routine'    => 'Routine Inspection',
        'follow_up'  => 'Follow-up Inspection',
        'complaint'  => 'Complaint Investigation',
        'joint'      => 'Joint Inspection',
        'emergency'  => 'Emergency Inspection',
    ];

    public const BUILDING_USES = [
        'hotel'        => 'Hotel',
        'apartment'    => 'Apartment',
        'commercial'   => 'Commercial Building',
        'hospital'     => 'Hospital',
        'health_centre'=> 'Health Centre',
        'school'       => 'School',
        'university'   => 'University',
        'market'       => 'Market',
        'restaurant'   => 'Restaurant',
        'industry'     => 'Industry',
        'office'       => 'Office Building',
        'church'       => 'Church',
        'residential'  => 'Residential House',
        'mixed_use'    => 'Mixed Use',
        'other'        => 'Other',
    ];

    public const SYSTEM_TYPES = [
        'stp'          => 'Sewage Treatment Plant (STP)',
        'septic_tank'  => 'Septic Tank',
        'public_sewer' => 'Public Sewer Connection',
        'soak_pit'     => 'Soak Pit',
        'bio_digester' => 'Bio-digester',
        'none'         => 'None',
        'other'        => 'Other',
    ];

    public const DESLUDGING_FREQUENCIES = [
        'monthly'        => 'Monthly',
        'quarterly'      => 'Quarterly',
        'every_6_months' => 'Every 6 Months',
        'annually'       => 'Annually',
        'as_needed'      => 'As Needed',
        'never'          => 'Never',
    ];

    public const SEPTIC_TANK_TYPES = [
        'single_chamber' => 'Single Chamber',
        'two_chamber'    => 'Two Chamber',
        'three_chamber'  => 'Three Chamber',
        'prefabricated'  => 'Prefabricated / Plastic Tank',
        'unknown'        => 'Unknown',
    ];

    public const SEPTIC_TANK_MATERIALS = [
        'concrete'    => 'Concrete',
        'masonry'     => 'Masonry / Brick',
        'plastic_hdpe'=> 'Plastic / HDPE',
        'fiberglass'  => 'Fiberglass',
        'other'       => 'Other',
    ];

    public const SEPTIC_TANK_LOCATIONS = [
        'inside_compound'  => 'Inside Compound',
        'outside_compound' => 'Outside Compound / Public Land',
        'under_building'   => 'Under Building',
        'other'            => 'Other',
    ];

    /* Component grids. Each component is rated on the same four-point
       scale, so both grids share one condition set. */
    public const CONDITION_RATINGS = [
        'good'          => 'Good',
        'fair'          => 'Fair',
        'poor'          => 'Poor',
        'not_available' => 'Not Available',
    ];

    public const SEPTIC_COMPONENTS = [
        'inlet_pipe'          => 'Inlet Pipe',
        'outlet_baffle'       => 'Outlet Pipe / Baffle Wall',
        'access_cover'        => 'Access Cover / Manhole',
        'vent_pipe'           => 'Vent Pipe',
        'scum_layer'          => 'Scum Layer',
        'sludge_layer'        => 'Sludge Layer',
    ];

    public const STP_COMPONENTS = [
        'screening_chamber' => 'Screening Chamber',
        'pumps'             => 'Pumps',
        'air_blowers'       => 'Air Blowers',
        'chlorination'      => 'Chlorination System',
        'oil_grease_trap'   => 'Oil & Grease Trap',
        'sludge_drying_bed' => 'Sludge Drying Bed',
        'flow_meter'        => 'Flow Meter',
        'ventilation'       => 'Ventilation',
    ];

    public const ABSORPTION_FIELD_CONDITIONS = [
        'good'           => 'Good',
        'fair'           => 'Fair',
        'poor'           => 'Poor',
        'not_applicable' => 'Not Applicable',
    ];

    public const EFFLUENT_DISPOSAL_METHODS = [
        'soak_pit'         => 'Soak Pit / Drain Field',
        'direct_discharge' => 'Direct Discharge to Drain / Ditch',
        'wetland'          => 'Wetland',
        'open_ground'      => 'Open Ground',
        'reuse'            => 'Reuse',
        'unknown'          => 'Unknown',
        'emptying'         => 'Emptying',
    ];

    public const TANK_ADEQUACY = [
        'adequate'             => 'Adequate',
        'undersized'           => 'Undersized',
        'unable_to_determine'  => 'Unable to Determine',
    ];

    public const OPERATIONAL_STATUSES = [
        'functional'            => 'Functional',
        'partially_functional'  => 'Partially Functional',
        'non_functional'        => 'Non-functional',
    ];

    public const EFFLUENT_STANDARDS = [
        'yes'        => 'Yes',
        'no'         => 'No',
        'not_tested' => 'Not Tested',
    ];

    public const DISCHARGE_POINTS = [
        'public_sewer'    => 'Public Sewer',
        'public_drainage' => 'Public Drainage',
        'wetland'         => 'Wetland',
        'river'           => 'River',
        'soak_pit'        => 'Soak Pit',
        'reuse'           => 'Reuse',
        'other'           => 'Other',
    ];

    public const POLLUTION_TYPES = [
        'bad_odour'          => 'Bad Odour',
        'overflow'           => 'Overflow',
        'high_turbidity'     => 'High Turbidity',
        'foam'               => 'Foam',
        'solid_waste'        => 'Solid Waste',
        'oil_grease'         => 'Oil & Grease',
        'dead_vegetation'    => 'Dead Vegetation',
        'wetland_pollution'  => 'Wetland Pollution',
        'river_pollution'    => 'River Pollution',
        'none'               => 'None',
    ];

    public const NON_COMPLIANCES = [
        'no_stp'                    => 'No STP',
        'stp_not_functional'        => 'STP Not Functional',
        'poor_maintenance'          => 'Poor Maintenance',
        'pumps_not_working'         => 'Pumps Not Working',
        'air_blower_faulty'         => 'Air Blower Faulty',
        'chlorination_not_working'  => 'Chlorination Not Working',
        'overflow'                  => 'Overflow',
        'illegal_discharge'         => 'Illegal Discharge',
        'no_effluent_test_results'  => 'No Effluent Test Results',
        'no_maintenance_records'    => 'No Maintenance Records',
        'capacity_insufficient'     => 'STP Capacity Insufficient',
        'uncovered_chambers'        => 'Uncovered Chambers',
        'poor_sludge_management'    => 'Poor Sludge Management',
        'other'                     => 'Other',
    ];

    public const COMPLIANCE_STATUSES = [
        'compliant'            => 'Compliant',
        'partially_compliant'  => 'Partially Compliant',
        'non_compliant'        => 'Non-Compliant',
    ];

    protected $fillable = [
        'inspection_id',
        'village', 'gps_lat', 'gps_lng', 'inspection_type',
        'facility_name', 'building_use', 'building_use_other',
        'system_type', 'system_type_other', 'design_capacity_m3',
        'population_served', 'water_consumption_m3', 'year_constructed',
        'maintenance_company', 'last_maintenance_date', 'last_desludging_date',
        'desludging_frequency',
        'septic_tank_type', 'septic_chamber_count', 'septic_tank_material',
        'septic_tank_material_other', 'septic_tank_location', 'septic_tank_location_other',
        'septic_components', 'has_absorption_field', 'absorption_field_condition',
        'effluent_disposal_method', 'signs_of_leakage', 'signs_of_overflow',
        'structural_damage_visible', 'odour_detected', 'desludging_provider',
        'tank_adequacy', 'septic_observations',
        'operational_status', 'stp_components', 'general_condition',
        'effluent_test_available', 'effluent_test_date', 'laboratory_name',
        'effluent_test_valid', 'effluent_meets_standards',
        'final_discharge_point', 'final_discharge_point_other',
        'untreated_discharge_evidence', 'pollution_evidence', 'pollution_types',
        'key_findings', 'environmental_risks',
        'non_compliances', 'non_compliances_other',
        'compliance_status', 'corrective_actions', 'compliance_deadline',
        'followup_inspection_date', 'additional_remarks',
        'facility_representative_name', 'facility_representative_comments',
    ];

    protected function casts(): array
    {
        return [
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'design_capacity_m3'   => 'decimal:2',
            'water_consumption_m3' => 'decimal:2',
            'last_maintenance_date'    => 'date',
            'last_desludging_date'     => 'date',
            'effluent_test_date'       => 'date',
            'compliance_deadline'      => 'date',
            'followup_inspection_date' => 'date',
            'has_absorption_field'         => 'boolean',
            'signs_of_leakage'             => 'boolean',
            'signs_of_overflow'            => 'boolean',
            'structural_damage_visible'    => 'boolean',
            'odour_detected'               => 'boolean',
            'effluent_test_available'      => 'boolean',
            'effluent_test_valid'          => 'boolean',
            'untreated_discharge_evidence' => 'boolean',
            'pollution_evidence'           => 'boolean',
            'septic_components' => 'array',
            'stp_components'    => 'array',
            'pollution_types'   => 'array',
            'non_compliances'   => 'array',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /** Whether Section 4 (Septic Tank) applies to what was found on site. */
    public function isSepticTank(): bool
    {
        return $this->system_type === 'septic_tank';
    }

    /** Whether Section 5 (Operational Status) applies — a treatment plant. */
    public function isTreatmentPlant(): bool
    {
        return $this->system_type === 'stp';
    }

    public function complianceLabel(): string
    {
        return self::COMPLIANCE_STATUSES[$this->compliance_status] ?? '—';
    }

    /**
     * The tone a status pill should take.
     *
     * Not a compliance band — there is no percentage here — but the same
     * three-colour logic every other verdict on this platform uses, so a
     * wastewater inspection's outcome reads consistently with everything
     * else in the register.
     */
    public function complianceTone(): string
    {
        return match ($this->compliance_status) {
            'compliant'           => 'good',
            'partially_compliant' => 'weak',
            'non_compliant'       => 'poor',
            default               => 'na',
        };
    }
}