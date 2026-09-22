<?php

namespace App\Models;

use App\Support\ComplianceBand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A site visit.
 *
 * An inspection is bound to the checklist version it was assessed under,
 * so its score can always be reproduced exactly as it stood on the day.
 * Editing a checklist weight afterwards creates a new version; it does
 * not rewrite what was already found.
 *
 * Where the premises is — district, sector, cell, coordinates — belongs
 * to the entity, not to the visit. A petrol station does not move
 * between inspections.
 */
class Inspection extends Model
{
    use SoftDeletes;

    public const DRAFT     = 'draft';
    public const COMPLETED = 'completed';

    protected $fillable = [
        'case_reference',
        'entity_id',
        'template_id',
        'type_code',
        'inspection_date',
        'visit_type',
        'previous_inspection_id',
        'visit_number',
        'status',
        'inspector_id',
        'inspector_name',
        'district_id',
        'observations',
        'recommendations',
        'owner_recommendations',
        'owner_rep_name',
        'earned_score',
        'possible_score',
        'compliance',
        'legacy_id',
        'deleted_by',
        'deleted_reason',
        'stage',
        'permit_number',
        'permit_expiry',
        'contractor',
        'supervisor',
        'building_status',
        'dwelling_unit',
        'has_physical_plan',
        'building_category',
        'on_main_corridor',
        'corridor_road',
        'application_number',
        'permit_issued_on',
        'reviewed_on',

        /* Wastewater — none of these could ever be saved before this
           line existed. Mass assignment silently drops any key not
           listed here; there is no error to point at when it happens,
           which is exactly what cost an entire night tracing one
           missing field. */
        'occupation_permit_number',
        'occupation_permit_year',
        'manager_name',
        'manager_telephone',
        'wastewater_system_type',
        'wastewater_system_type_other',
        'stp_type',
        'stp_type_other',
        'design_capacity_m3',
        'population_served',
        'water_consumption_m3',
        'year_constructed',
        'last_maintenance_date',
        'last_desludging_date',
        'desludging_frequency',
        'operational_status',
        'stp_general_condition',
        'effluent_test_date',
        'laboratory_name',
        'final_discharge_point',
        'septic_tank_type',
        'septic_chamber_count',
        'septic_tank_material',
        'septic_tank_capacity',
        'septic_year_installed',
        'distance_to_foundation_m',
        'septic_tank_location',
        'absorption_field_condition',
        'effluent_disposal_method',
        'desludging_provider',
        'tank_adequacy',
        'septic_observations',
        'non_compliances',
        'non_compliances_other',
        'key_findings',
        'environmental_risks',
        'wastewater_compliance_status',
        'decision_taken',
        'wastewater_fine_amount',
        'wastewater_compliance_deadline',
        'wastewater_followup_date',
    ];

       protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'earned_score'    => 'decimal:2',
            'possible_score'  => 'decimal:2',
            'compliance'      => 'decimal:2',
            'permit_expiry'     => 'date',
            'has_physical_plan' => 'boolean',
            'on_main_corridor' => 'boolean',
            'permit_issued_on' => 'date',
            'reviewed_on'      => 'date',

            /* Without these, the blade partial's optional($inspection
               ->last_maintenance_date)->format(...) calls would fatal
               the moment any of these actually held a value — optional()
               guards against null, not against a plain, uncast string. */
            'last_maintenance_date'          => 'date',
            'last_desludging_date'           => 'date',
            'effluent_test_date'             => 'date',
            'wastewater_compliance_deadline' => 'date',
            'wastewater_followup_date'       => 'date',
            'non_compliances'                => 'array',
        ];
    }

    /* ==============================================================
       Relations
       ============================================================== */

    public function entity(): BelongsTo   { return $this->belongsTo(Entity::class); }
    public function template(): BelongsTo { return $this->belongsTo(ChecklistTemplate::class, 'template_id'); }
    public function inspector(): BelongsTo{ return $this->belongsTo(User::class, 'inspector_id'); }
    public function previous(): BelongsTo { return $this->belongsTo(Inspection::class, 'previous_inspection_id'); }

    public function answers(): HasMany { return $this->hasMany(InspectionAnswer::class); }

    public function team(): HasMany
    {
        return $this->hasMany(InspectionTeamMember::class)
            ->orderByDesc('is_lead')
            ->orderBy('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionPhoto::class)->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /* ==============================================================
       State
       ============================================================== */

    public function isFollowUp(): bool { return $this->visit_type === 'followup'; }
    public function isDraft(): bool    { return $this->status === self::DRAFT; }
    public function isComplete(): bool { return $this->status === self::COMPLETED; }

    /* ==============================================================
       Scoring
       ============================================================== */

    /**
     * Recompute the score from the recorded answers.
     *
     * Items marked not applicable are excluded from both the earned and
     * the possible total, so a requirement that does not apply neither
     * helps nor penalises.
     *
     * A prohibition item is scored the other way about: it asks whether
     * something forbidden is present — a kitchen at a petrol station —
     * so "no" earns the points. Scoring it the usual way would award
     * marks for the hazard itself.
     *
     * The result is stored rather than computed on demand, because a
     * report must reproduce the figure arrived at on the day, not one
     * recalculated under a later version of the checklist.
     */
    public function recalculate(): void
    {
        $earned   = 0.0;
        $possible = 0.0;

        foreach ($this->answers()->with('item')->get() as $answer) {
            if (! in_array($answer->status, ['yes', 'no'], true)) {
                continue;
            }

            $item = $answer->item;

            if (! $item) {
                continue;
            }

            $weight    = (float) $item->max_score;
            $possible += $weight;

            $complied = $item->is_prohibition
                ? $answer->status === 'no'
                : $answer->status === 'yes';

            if ($complied) {
                $earned += $weight;
            }
        }

        $this->earned_score   = round($earned, 2);
        $this->possible_score = round($possible, 2);
        $this->compliance     = $possible > 0 ? round(100 * $earned / $possible, 2) : 0;

        $this->save();
    }
    
        /**
     * The Wastewater Management Inspection detail record, where this
     * inspection is one. One row per inspection — held here rather than
     * on the shared answers table, since nothing about this category is
     * a checklist item with a score.
     */
    public function wastewaterInspection(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\WastewaterInspection::class);
    }

    /** How many checklist items are still unanswered. */
    public function unanswered(): int
    {
        if (! $this->template) {
            return 0;
        }

        $total = $this->template->sections()
            ->withCount('items')->get()->sum('items_count');

        return max(0, $total - $this->answers()->whereNotNull('status')->count());
    }
    
    public function fines(): HasMany
    {
        return $this->hasMany(\App\Models\Fine::class);
    }

    /* ==============================================================
       What the score means
       ============================================================== */

    /**
     * The consequence this score carries.
     *
     * Held in ComplianceBand so the thresholds are defined once. They
     * decide what happens to a premises, so they must not be restated in
     * each view that displays them.
     */
        /**
     * Items where the requirement is about where the station physically
     * sits, not how it is run. Plot size, clearance from power lines,
     * distance from sensitive areas — none of these can be corrected by
     * better housekeeping, and a station sited wrongly does not become
     * rightly sited by scoring well elsewhere. Failing any one of them
     * means permanent closure regardless of the overall percentage.
     */
    public const PETROL_SITING_ITEMS = [51, 55, 56];
    public const PETROL_WETLAND_ZONING_CODES = ['W2', 'W3', 'W4', 'W5', 'WB'];

    public function band(): array
    {
        if ($this->hasSitingViolation()) {
            return ComplianceBand::BANDS['permanent'] + [
                'key'  => 'permanent',
                'note' => 'Siting requirement not met — the location itself '
                        . 'does not comply, whatever the overall score.',
            ];
        }

        return ComplianceBand::for((float) $this->compliance);
    }

    /**
     * Whether a petrol station failed one of the siting requirements —
     * plot size, power line clearance, or distance from sensitive areas.
     * Checked only for petrol stations; every other category is judged
     * on its score alone.
     */
        public function hasSitingViolation(): bool
    {
        if ($this->type_code !== 'petrol') {
            return false;
        }

        if (in_array($this->entity?->zoning, self::PETROL_WETLAND_ZONING_CODES, true)) {
            return true;
        }

        return $this->answers()
            ->whereIn('item_id', self::PETROL_SITING_ITEMS)
            ->where('status', 'no')
            ->exists();
    }

    /** ['Improvement notice and fine', 'weak'] — label and tone. */
    public function deliberation(): array
    {
        $band = $this->band();

        return [$band['label'], $band['tone']];
    }

        public function isCompliant(): bool
    {
        if ($this->hasSitingViolation()) {
            return false;
        }

        return ComplianceBand::isCompliant((float) $this->compliance);
    }

    /* ==============================================================
       Who may act on it
       ============================================================== */

    /**
     * Was this inspection carried out by the given officer?
     *
     * A report or letter may only be drafted and edited by an officer who
     * was there. A supervisor may verify, return and sign — but writing
     * findings for a site one did not visit is a different thing, and the
     * platform should not permit it.
     *
     * The name is matched as well as the account, because team members
     * were recorded by name before every officer had an account.
     */
    public function conductedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->inspector_id === $user->id) {
            return true;
        }

        return $this->team()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($user->name))]);
            })
            ->exists();
    }

    /** The officer recorded as leading the visit. */
    public function lead(): ?InspectionTeamMember
    {
        return $this->team->firstWhere('is_lead', true) ?? $this->team->first();
    }

    /* ==============================================================
       Documents
       ============================================================== */

    /** The report for this inspection, if one has been drafted. */
    public function report(): ?Document
    {
        return $this->documents()->where('type', 'report')->latest('id')->first();
    }

    /** Has the report been signed by the team and the Director? */
    public function hasSealedReport(): bool
    {
        return (bool) $this->report()?->sealed_at;
    }

    public function letters(): HasMany
    {
        return $this->documents()->where('type', 'letter');
    }

    /* ==============================================================
       Location — held on the premises, read through it here
       ============================================================== */

    public function districtName(): ?string { return $this->entity?->district; }
    public function sector(): ?string       { return $this->entity?->sector; }
    public function cell(): ?string         { return $this->entity?->cell; }

    /** 'Gasabo · Gatsata · Nyamabuye' */
    public function locationLabel(): string
    {
        $parts = array_filter([
            $this->entity?->district,
            $this->entity?->sector,
            $this->entity?->cell,
        ]);

        return $parts ? implode(' · ', $parts) : '—';
    }

    public function hasCoordinates(): bool
    {
        return filled($this->entity?->latitude) && filled($this->entity?->longitude);
    }

    /* ==============================================================
       Scopes
       ============================================================== */

    public function scopeCompleted($query)
    {
        return $query->where('status', self::COMPLETED);
    }

    public function scopeOfType($query, string $code)
    {
        return $query->where('type_code', $code);
    }

    /** Inspections an officer may see: their own district, or all. */
    public function scopeVisibleTo($query, User $user)
    {
        return $user->isCityWide()
            ? $query
            : $query->where('district_id', $user->district_id);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('inspection_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('inspection_date', '<=', $to));
    }

    /* ==============================================================
       Display
       ============================================================== */

    /** 'Second visit', 'First visit'. */
    public function visitLabel(): string
    {
        return match ((int) $this->visit_number) {
            0, 1    => 'First visit',
            2       => 'Second visit',
            3       => 'Third visit',
            default => 'Visit ' . $this->visit_number,
        };
    }
        /**
     * Contraventions recorded at this inspection.
     *
     * Distinct from the checklist: a fault is a specific breach with a
     * penalty from the schedule, not a requirement that was scored.
     */
    public function faults(): HasMany
    {
        return $this->hasMany(InspectionFault::class);
    }

    /** What the schedule says is owed for the faults found. */
    public function fineTotal(): float
    {
        return (float) $this->faults->sum('amount');
    }
}