<?php

namespace App\Services;

use App\Models\Entity;
use App\Models\Inspection;
use Illuminate\Support\Facades\DB;

/**
 * Statistics over the platform's own inspection records.
 *
 * Replaces the read-only legacy queries now that inspections are held
 * natively. Everything counts completed inspections only — a draft is
 * an unfinished assessment and would distort compliance figures.
 */
class InspectionStats
{
    
    
    
    /**
     * Premises grouped by sector within a district.
     *
     * The district panel shows districts until one is chosen, then the
     * sectors within it — so the same chart answers "which district is
     * worst" and "where in Gasabo" without becoming two controls.
     */
    public function bySector(array $f = []): array
    {
        $latest = collect($this->latestPerPremises($f));

        if ($latest->isEmpty()) {
            return [];
        }

        return $latest
            ->filter(fn ($p) => filled($p->sector))
            ->groupBy('sector')
            ->map(fn ($rows, $sector) => (object) [
                'name'       => $sector,
                'premises'   => $rows->count(),
                'compliance' => round($rows->avg(fn ($r) => (float) $r->compliance), 1),
            ])
            ->sortBy('compliance')
            ->values()
            ->all();
    }

    /**
     * Premises grouped by district, counted once each.
     *
     * byDistrict() counts inspections; this counts premises, which is
     * what every other figure on the activity dashboards now uses.
     */
    public function districtsByPremises(array $f = []): array
    {
        $latest = collect($this->latestPerPremises($f));

        if ($latest->isEmpty()) {
            return [];
        }

        return $latest
            ->filter(fn ($p) => filled($p->district))
            ->groupBy('district')
            ->map(fn ($rows, $district) => (object) [
                'name'       => $district,
                'premises'   => $rows->count(),
                'compliance' => round($rows->avg(fn ($r) => (float) $r->compliance), 1),
            ])
            ->sortBy('compliance')
            ->values()
            ->all();
    }
    
    /**
     * SQL fragment: did this answer comply?
     *
     * A prohibition item inverts, so "no" is the compliant answer.
     */
    private const COMPLIED = "((ci.is_prohibition = 0 AND ia.status = 'yes')
                            OR (ci.is_prohibition = 1 AND ia.status = 'no'))";
    /** Base query: completed, not deleted, optionally scoped. */
    private function base(array $f = [])
    {
        $q = Inspection::query()
            ->join('entities', 'entities.id', '=', 'inspections.entity_id')
            ->whereNull('inspections.deleted_at')
            ->where('inspections.status', 'completed');

        foreach ([
            'type'     => 'inspections.type_code',
            'district' => 'entities.district',
            'sector'   => 'entities.sector',
            'cell'     => 'entities.cell',
        ] as $key => $column) {
            if (! empty($f[$key])) {
                $q->where($column, $f[$key]);
            }
        }
        if (! empty($f['from'])) {
            $q->whereDate('inspections.inspection_date', '>=', $f['from']);
        }

        if (! empty($f['to'])) {
            $q->whereDate('inspections.inspection_date', '<=', $f['to']);
        }    

        return $q;
    }
    
    /**
     * The most recent inspection of each premises within the filters.
     *
     * A premises visited three times should count once, judged on where
     * it stands now — not three times, weighted by how often it happened
     * to be revisited.
     */
        public function latestPerPremises(array $f = []): array
    {
        $ids = (clone $this->base($f))->pluck('inspections.id');

        if ($ids->isEmpty()) {
            return [];
        }

        $rows = DB::table(DB::raw('(
                SELECT i.id, i.entity_id, i.compliance, i.inspection_date, i.inspector_name,
                       i.visit_number, i.case_reference,
                       ROW_NUMBER() OVER (
                           PARTITION BY i.entity_id
                           ORDER BY i.inspection_date DESC, i.id DESC
                       ) AS rn
                FROM inspections i
                WHERE i.id IN (' . $ids->implode(',') . ')
            ) AS latest'))
            ->join('entities as e', 'e.id', '=', 'latest.entity_id')
            ->where('latest.rn', 1)
            ->select(
                'latest.id as inspection_id', 'latest.compliance', 'latest.inspection_date',
                'latest.inspector_name', 'latest.visit_number', 'latest.case_reference',
                'e.id as entity_id', 'e.name', 'e.upi', 'e.owner', 'e.telephone', 'e.email',
                'e.district', 'e.sector', 'e.cell', 'e.latitude', 'e.longitude'
            )
            ->orderBy('latest.compliance')
            ->get();

        /* Petrol stations may be sited wrongly — plot size, power line
           clearance, distance from sensitive areas — in which case the
           premises is permanently closed whatever its percentage says.
           Checked once here, against the same items Inspection::band()
           uses on a single inspection's own page, so the dashboard and
           that page can never disagree about which stations qualify. */
        $sitingItems = ($f['type'] ?? null) === 'petrol'
            ? \App\Models\Inspection::PETROL_SITING_ITEMS
            : [];

                $violated = $sitingItems
            ? DB::table('inspection_answers')
                ->whereIn('inspection_id', $rows->pluck('inspection_id'))
                ->whereIn('item_id', $sitingItems)
                ->where('status', 'no')
                ->distinct()
                ->pluck('inspection_id')
                ->flip()
            : collect();

        /* Rows are entity+inspection pairs already; the query joined
           entities in, but did not select zoning — pull it separately
           rather than widen every caller's select just for this. */
        $wetlandEntities = $sitingItems
            ? DB::table('entities')
                ->whereIn('id', $rows->pluck('entity_id'))
                ->whereIn('zoning', \App\Models\Inspection::PETROL_WETLAND_ZONING_CODES)
                ->pluck('id')
                ->flip()
            : collect();

        return $rows->map(function ($row) use ($violated, $wetlandEntities) {
            $row->has_siting_violation = $violated->has($row->inspection_id)
                || $wetlandEntities->has($row->entity_id);
            return $row;
        })->all();
    }

    /**
     * Checklist criteria scored by premises, not by answer.
     *
     * "Fourteen stations lack a fire extinguisher" is actionable.
     * "Twenty-two answers recorded no" is not, because some of those
     * answers belong to the same station on different visits.
     */
    public function criteriaByPremises(array $f, array $sectionNumbers = []): array
    {
        $latest = collect($this->latestPerPremises($f))->pluck('inspection_id');

        if ($latest->isEmpty()) {
            return [];
        }

        return DB::table('inspection_answers as ia')
            ->join('checklist_items as ci', 'ci.id', '=', 'ia.item_id')
            ->join('checklist_sections as cs', 'cs.id', '=', 'ci.section_id')
            ->whereIn('ia.inspection_id', $latest)
            ->when($sectionNumbers, fn ($q) => $q->whereIn('cs.section_number', $sectionNumbers))
            ->groupBy('ci.id', 'ci.label', 'ci.max_score', 'cs.section_number', 'cs.title',
                      'cs.sort_order', 'ci.sort_order')
            ->selectRaw("ci.id AS item_id,
                         ci.label,
                         ci.max_score,
                         cs.section_number,
                         cs.title AS section_title,
                         SUM(ia.status IN ('yes','no')) AS premises_assessed,
                         SUM(" . self::COMPLIED . ")         AS premises_passed,
                         SUM(ia.status IN ('yes','no') AND NOT " . self::COMPLIED . ")          AS premises_failed,
                         SUM(ia.status = 'na')          AS not_applicable,
                         ROUND(100 * SUM(" . self::COMPLIED . ")
                               / NULLIF(SUM(ia.status IN ('yes','no')), 0)) AS compliance")
            ->orderBy('cs.sort_order')->orderBy('ci.sort_order')
            ->get()->all();
    }

    /** Checklist sections scored by premises. */
    public function sectionsByPremises(array $f = []): array
    {
        $latest = collect($this->latestPerPremises($f))->pluck('inspection_id');

        if ($latest->isEmpty()) {
            return [];
        }

        return DB::table('inspection_answers as ia')
            ->join('checklist_items as ci', 'ci.id', '=', 'ia.item_id')
            ->join('checklist_sections as cs', 'cs.id', '=', 'ci.section_id')
            ->whereIn('ia.inspection_id', $latest)
            ->whereIn('ia.status', ['yes', 'no'])
            ->groupBy('cs.id', 'cs.section_number', 'cs.title', 'cs.sort_order')
            ->selectRaw("cs.section_number, cs.title,
                         ROUND(100 * SUM(CASE WHEN " . self::COMPLIED . " THEN ci.max_score ELSE 0 END)
                               / NULLIF(SUM(ci.max_score), 0)) AS compliance,
                         SUM(ia.status='no') AS failures")
            ->orderBy('compliance')
            ->get()->all();
    }

    /**
     * Siting against operations, by premises.
     * Section numbers come from config so each category can differ.
     */
    public function sitingVsOperationsByPremises(array $f, string $code): array
    {
        $groups = config('inspection_types.section_groups.' . $code, []);
        $rows   = collect($this->sectionsByPremises($f));

        $avg = function (array $keys) use ($rows) {
            $v = $rows->whereIn('section_number', $keys)
                      ->pluck('compliance')->filter(fn ($c) => $c !== null);

            return $v->count() ? (int) round($v->avg()) : 0;
        };

        return [
            'siting'     => $avg($groups['siting'] ?? []),
            'operations' => $avg($groups['operations'] ?? []),
        ];
    }

    public function overview(array $f = []): array
    {
        $rows = (clone $this->base($f))
            ->select('inspections.compliance', 'inspections.entity_id')
            ->get();

        $scores = $rows->pluck('compliance')->map(fn ($v) => (float) $v);

        $dates = (clone $this->base($f))
            ->selectRaw('MIN(inspections.inspection_date) AS first_date, MAX(inspections.inspection_date) AS last_date')
            ->first();

        return [
            'inspections' => $rows->count(),
            'entities'    => $rows->pluck('entity_id')->unique()->count(),
            'first_date'  => $dates->first_date ?? null,
            'last_date'   => $dates->last_date ?? null,
            'mean'        => $scores->count() ? round($scores->avg(), 1) : 0,
            'lowest'      => $scores->count() ? round($scores->min(), 1) : 0,
            'highest'     => $scores->count() ? round($scores->max(), 1) : 0,
            'below70'     => $scores->filter(fn ($v) => $v < 70)->count(),
            'compliant'   => $scores->filter(fn ($v) => $v >= 70)->count(),
            'drafts'      => Inspection::where('status', 'draft')
                                ->when(! empty($f['type']), fn ($q) => $q->where('type_code', $f['type']))
                                ->count(),
        ];
    }

    /** Spread of scores across compliance bands. */
    public function distribution(array $f = []): array
    {
        $scores = (clone $this->base($f))->pluck('inspections.compliance')->map(fn ($v) => (float) $v);

        $bands = ['Below 50%' => 0, '50 to 69%' => 0, '70 to 84%' => 0, '85% and above' => 0];

        foreach ($scores as $s) {
            if ($s < 50)     $bands['Below 50%']++;
            elseif ($s < 70) $bands['50 to 69%']++;
            elseif ($s < 85) $bands['70 to 84%']++;
            else             $bands['85% and above']++;
        }

        return $bands;
    }
/**
     * Every checklist item within a set of sections, with how often it
     * was assessed and how often it failed.
     *
     * Used by the criteria drill-down: an officer sees which specific
     * requirement is failing rather than only that a section scores low.
     */
    public function itemsInSections(array $f, array $sectionNumbers = []): array
    {
        $ids = (clone $this->base($f))->pluck('inspections.id');

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('inspection_answers as ia')
            ->join('checklist_items as ci', 'ci.id', '=', 'ia.item_id')
            ->join('checklist_sections as cs', 'cs.id', '=', 'ci.section_id')
            ->whereIn('ia.inspection_id', $ids)
            ->when($sectionNumbers, fn ($q) => $q->whereIn('cs.section_number', $sectionNumbers))
            ->groupBy('ci.id', 'ci.label', 'ci.max_score', 'cs.section_number', 'cs.title', 'cs.sort_order', 'ci.sort_order')
            ->selectRaw("ci.id AS item_id,
                         ci.label,
                         ci.max_score,
                         cs.section_number,
                         cs.title AS section_title,
                         SUM(ia.status IN ('yes','no'))  AS assessed,
                         SUM(" . self::COMPLIED . ")          AS passed,
                         SUM(ia.status IN ('yes','no') AND NOT " . self::COMPLIED . ")          AS failed,
                         SUM(ia.status = 'na')           AS not_applicable,
                         ROUND(100 * SUM(" . self::COMPLIED . ")
                               / NULLIF(SUM(ia.status IN ('yes','no')), 0)) AS compliance")
            ->orderBy('cs.sort_order')->orderBy('ci.sort_order')
            ->get()->all();
    }

    /**
     * The premises that failed one particular requirement.
     *
     * This is what an inspector needs before a follow-up round: not a
     * percentage, but the names and addresses to visit.
     */
    public function entitiesFailingItem(array $f, int $itemId, string $status = 'no'): array
    {
        $ids = (clone $this->base($f))->pluck('inspections.id');

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('inspection_answers as ia')
            ->join('inspections as i', 'i.id', '=', 'ia.inspection_id')
            ->join('entities as e', 'e.id', '=', 'i.entity_id')
            ->whereIn('ia.inspection_id', $ids)
            ->where('ia.item_id', $itemId)
            ->where('ia.status', $status)
            ->select(
                'e.id as entity_id', 'e.name', 'e.upi', 'e.owner', 'e.telephone', 'e.email',
                'e.district', 'e.sector', 'e.cell',
                'i.id as inspection_id', 'i.inspection_date', 'i.compliance',
                'i.inspector_name', 'i.case_reference',
                'ia.comment'
            )
            ->orderBy('i.compliance')
            ->get()->all();
    }

    /** One checklist item, for a page heading. */
    public function item(int $itemId): ?object
    {
        return DB::table('checklist_items as ci')
            ->join('checklist_sections as cs', 'cs.id', '=', 'ci.section_id')
            ->where('ci.id', $itemId)
            ->select('ci.id', 'ci.label', 'ci.max_score',
                     'cs.section_number', 'cs.title as section_title')
            ->first();
    }
    /** Mean compliance and volume by district. */
    public function byDistrict(array $f = []): array
    {
        return (clone $this->base($f))
            ->whereNotNull('entities.district')
            ->where('entities.district', '<>', '')
            ->groupBy('entities.district')
            ->selectRaw('entities.district AS district,
                         COUNT(*) AS inspections,
                         ROUND(AVG(inspections.compliance), 1) AS compliance')
            ->orderByDesc('compliance')
            ->get()->all();
    }

    /**
     * Compliance by checklist section.
     *
     * Weighted by item score, with not-applicable answers excluded from
     * both the earned and the possible total.
     */
    public function bySection(array $f = []): array
    {
        $ids = (clone $this->base($f))->pluck('inspections.id');

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('inspection_answers as ia')
            ->join('checklist_items as ci', 'ci.id', '=', 'ia.item_id')
            ->join('checklist_sections as cs', 'cs.id', '=', 'ci.section_id')
            ->whereIn('ia.inspection_id', $ids)
            ->whereIn('ia.status', ['yes', 'no'])
            ->groupBy('cs.id', 'cs.section_number', 'cs.title', 'cs.sort_order')
            ->selectRaw("cs.section_number, cs.title,
                         ROUND(100 * SUM(CASE WHEN " . self::COMPLIED . " THEN ci.max_score ELSE 0 END)
                               / NULLIF(SUM(ci.max_score), 0)) AS compliance")
            ->orderBy('compliance')
            ->get()->all();
    }

    /** Requirements failed most often. */
    public function topFailures(array $f = [], int $limit = 10): array
    {
        $ids = (clone $this->base($f))->pluck('inspections.id');

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('inspection_answers as ia')
            ->join('checklist_items as ci', 'ci.id', '=', 'ia.item_id')
            ->whereIn('ia.inspection_id', $ids)
            ->whereIn('ia.status', ['yes', 'no'])
            ->groupBy('ci.id', 'ci.label')
            ->selectRaw("ci.label,
                         SUM(ia.status='no') AS failures,
                         COUNT(*) AS assessed,
                         ROUND(100 * SUM(ia.status='no') / COUNT(*)) AS fail_pct")
            ->havingRaw("SUM(ia.status='no') > 0")
            ->orderByDesc('failures')->orderByDesc('fail_pct')
            ->limit($limit)
            ->get()->all();
    }

    /** Lowest-scoring premises, for the follow-up list. */
    public function lowestScoring(array $f = [], int $limit = 8): array
    {
        return (clone $this->base($f))
            ->select(
                'inspections.id', 'inspections.compliance', 'inspections.inspection_date',
                'entities.id as entity_id', 'entities.name', 'entities.district', 'entities.sector'
            )
            ->orderBy('inspections.compliance')
            ->limit($limit)
            ->get()->all();
    }

    /** Volume and mean compliance per inspection category. */
    public function byType(): array
    {
        $rows = Inspection::query()
            ->whereNull('deleted_at')->where('status', 'completed')
            ->groupBy('type_code')
            ->selectRaw('type_code, COUNT(*) AS inspections, ROUND(AVG(compliance),1) AS compliance')
            ->get()->keyBy('type_code');

        $out = [];

        foreach (config('inspection_types.groups') as $group) {
            foreach ($group['types'] as $code => $t) {
                if (! $t['live']) {
                    continue;
                }
                $r = $rows->get($code);
                $out[] = (object) [
                    'code'        => $code,
                    'name'        => $t['name'],
                    'inspections' => $r->inspections ?? 0,
                    'compliance'  => $r->compliance ?? null,
                ];
            }
        }

        return $out;
    }

    /** Premises with coordinates, for the map. */
    public function mapped(array $f = []): array
    {
        return (clone $this->base($f))
            ->whereNotNull('entities.latitude')
            ->whereNotNull('entities.longitude')
            ->select(
                'entities.id as entity_id', 'entities.name', 'entities.latitude', 'entities.longitude',
                'entities.district', 'entities.sector',
                'inspections.id as inspection_id', 'inspections.compliance', 'inspections.inspection_date'
            )
            ->orderByDesc('inspections.inspection_date')
            ->get()->unique('entity_id')->values()->all();
    }

    /** Inspection volume by month. */
    public function byMonth(array $f = []): array
    {
        return (clone $this->base($f))
            ->groupByRaw("DATE_FORMAT(inspections.inspection_date, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(inspections.inspection_date, '%Y-%m') AS month, COUNT(*) AS inspections")
            ->orderBy('month')
            ->get()->all();
    }

    /** Distinct locations for the filter selectors. */
    public function locations(array $f = []): array
    {
        $col = fn (string $c) => Entity::query()
            ->when(! empty($f['type']), fn ($q) => $q->where('type_code', $f['type']))
            ->whereNotNull($c)->where($c, '<>', '')
            ->distinct()->orderBy($c)->pluck($c)->all();

        return ['districts' => $col('district'), 'sectors' => $col('sector'), 'cells' => $col('cell')];
    }

    /** Siting versus operational compliance, for petrol stations. */
    public function sitingVsOperations(array $f = []): array
    {
        $siting     = ['1', '2', '3', '4', '5', '13'];
        $operations = ['6', '7', '8', '9', '10', '11', '12', '14', '15'];
        $sections   = $this->bySection($f);

        $avg = function (array $keys) use ($sections) {
            $v = [];
            foreach ($sections as $s) {
                if (in_array($s->section_number, $keys, true) && $s->compliance !== null) {
                    $v[] = (float) $s->compliance;
                }
            }
            return $v ? (int) round(array_sum($v) / count($v)) : 0;
        };

        return ['siting' => $avg($siting), 'operations' => $avg($operations)];
    }

    public static function band(float $pct): string
    {
        if ($pct >= 85) return 'good';
        if ($pct >= 70) return 'fair';
        if ($pct >= 50) return 'weak';
        return 'poor';
    }

    public static function deliberation(float $pct): array
    {
        if ($pct >= 85) return ['Compliant', 'good'];
        if ($pct >= 70) return ['Minor observations', 'fair'];
        if ($pct >= 50) return ['Corrective notice', 'weak'];
        return ['Enforcement action', 'poor'];
    }
    
        /**
     * Premises available for a follow-up.
     *
     * Read from this platform rather than the legacy database: once
     * inspections are being recorded here, a follow-up should attach to
     * the record the officer will actually see.
     *
     * Compliance is taken from the latest inspection, not averaged across
     * visits — a premises that has corrected its faults should show as
     * corrected, not dragged down by the visit that found them.
     */
    public function premisesForFollowUp(string $code, ?string $search = null, int $limit = 15): array
    {
        $q = \App\Models\Entity::query()
            ->where('type_code', $code)
            ->whereNull('deleted_at')
            ->withCount(['inspections' => fn ($i) => $i->where('status', 'completed')])
            ->with(['inspections' => fn ($i) => $i->where('status', 'completed')
                ->latest('inspection_date')->limit(1)]);

        if (filled($search)) {
            $s = '%' . trim($search) . '%';
            $q->where(fn ($w) => $w
                ->where('name', 'like', $s)
                ->orWhere('upi', 'like', $s)
                ->orWhere('owner', 'like', $s)
                ->orWhere('telephone', 'like', $s)
                ->orWhere('email', 'like', $s));
            $limit = 30;
        }

        return $q->orderBy('name')->limit($limit)->get()
            ->map(function ($e) {
                $last = $e->inspections->first();

                return (object) [
                    'entity_id'        => $e->id,
                    'name'             => $e->name,
                    'upi'              => $e->upi,
                    'owner'            => $e->owner,
                    'district'         => $e->district,
                    'sector'           => $e->sector,
                    'telephone'        => $e->telephone,
                    'email'            => $e->email,
                    'inspection_count' => $e->inspections_count,
                    'last_inspection'  => $last?->inspection_date?->format('j M Y'),
                    'compliance'       => $last ? round((float) $last->compliance, 1) : null,
                ];
            })
            ->all();
    }
}
