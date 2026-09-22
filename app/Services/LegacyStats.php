<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Read-only queries against the legacy inspection database.
 *
 * Filters: ['type' => 2, 'district' => .., 'sector' => .., 'cell' => ..]
 * All values are bound parameters. Nothing here writes.
 */
class LegacyStats
{
    private function db()
    {
        return DB::connection('legacy');
    }

    private function scope(array $f, string $alias = 'i'): array
    {
        $sql  = " WHERE $alias.deleted_at IS NULL ";
        $bind = [];

        foreach ([
            'type' => 'e.entity_type_id', 'district' => 'e.district',
            'sector' => 'e.sector', 'cell' => 'e.cell',
        ] as $key => $col) {
            if (! empty($f[$key])) {
                $sql .= " AND $col = ? ";
                $bind[] = $f[$key];
            }
        }

        return [$sql, $bind];
    }

    /* =========================================================
       Register — one row per premises, in the CoK report format
       ========================================================= */

    /**
     * The inspection register.
     *
     * One row per premises, showing how many times it has been
     * inspected and the result of the most recent visit.
     */
    public function register(array $f = []): array
    {
        [$where, $bind] = $this->scope($f);

        return $this->db()->select("
            SELECT
                e.id                AS entity_id,
                e.upi,
                e.zoning,
                e.owner,
                e.name,
                e.district,
                e.sector,
                e.telephone,
                e.email,
                COUNT(DISTINCT i.id)      AS inspection_count,
                MAX(i.inspection_date)    AS last_inspection,
                MIN(i.inspection_date)    AS first_inspection,
                ROUND(
                    SUM(CASE WHEN ia.status='yes' THEN ci.max_score ELSE 0 END)
                    / NULLIF(COUNT(DISTINCT i.id),0), 1
                ) AS compliance
            FROM entities e
            JOIN inspections i         ON i.entity_id = e.id
            JOIN inspection_answers ia ON ia.inspection_id = i.id
            JOIN checklist_items ci    ON ci.id = ia.item_id
            $where AND e.deleted_at IS NULL
            GROUP BY e.id
            ORDER BY compliance ASC", $bind);
    }

    /* =========================================================
       Detail — everything known about one premises
       ========================================================= */

    public function entity(int $id): ?object
    {
        return $this->db()->selectOne("
            SELECT e.*, et.name AS type_name, et.code AS type_code
            FROM entities e
            JOIN entity_types et ON et.id = e.entity_type_id
            WHERE e.id = ? AND e.deleted_at IS NULL", [$id]);
    }

    /** Every inspection carried out on this premises, newest first. */
    public function entityInspections(int $id): array
    {
        return $this->db()->select("
            SELECT i.id, i.inspection_date, i.inspector_name, i.status,
                   i.observations, i.recommendations, i.owner_recommendations,
                   i.owner_rep_name,
                   ROUND(SUM(CASE WHEN ia.status='yes' THEN ci.max_score ELSE 0 END),1) AS score,
                   SUM(ia.status='yes') AS yes_count,
                   SUM(ia.status='no')  AS no_count,
                   SUM(ia.status='na')  AS na_count
            FROM inspections i
            JOIN inspection_answers ia ON ia.inspection_id = i.id
            JOIN checklist_items ci    ON ci.id = ia.item_id
            WHERE i.entity_id = ? AND i.deleted_at IS NULL
            GROUP BY i.id
            ORDER BY i.inspection_date DESC, i.id DESC", [$id]);
    }

    /** The completed checklist for one inspection, grouped by section. */
    public function inspectionChecklist(int $inspectionId): array
    {
        $rows = $this->db()->select("
            SELECT cs.section_number, cs.title AS section_title, cs.sort_order AS section_order,
                   ci.item_code, ci.label, ci.max_score,
                   ia.status, ia.comment
            FROM inspection_answers ia
            JOIN checklist_items ci    ON ci.id = ia.item_id
            JOIN checklist_sections cs ON cs.id = ci.section_id
            WHERE ia.inspection_id = ?
            ORDER BY cs.sort_order, ci.sort_order", [$inspectionId]);

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r->section_number] ??= [
                'number' => $r->section_number,
                'title'  => $r->section_title,
                'items'  => [], 'earned' => 0, 'possible' => 0,
            ];
            $grouped[$r->section_number]['items'][] = $r;

            if (in_array($r->status, ['yes', 'no'], true)) {
                $grouped[$r->section_number]['possible'] += (float) $r->max_score;
                if ($r->status === 'yes') {
                    $grouped[$r->section_number]['earned'] += (float) $r->max_score;
                }
            }
        }

        return $grouped;
    }

    /** Team members recorded on an inspection. */
    public function inspectionTeam(int $inspectionId): array
    {
        return $this->db()->select("
            SELECT name, institution FROM inspection_team
            WHERE inspection_id = ? ORDER BY id", [$inspectionId]);
    }

    /* =========================================================
       Aggregates
       ========================================================= */

    public function overview(array $f = []): array
    {
        [$where, $bind] = $this->scope($f);

        $row = $this->db()->selectOne("
            SELECT COUNT(DISTINCT i.id) AS inspections, COUNT(DISTINCT e.id) AS entities,
                   MIN(i.inspection_date) AS first_date, MAX(i.inspection_date) AS last_date
            FROM inspections i JOIN entities e ON e.id = i.entity_id $where", $bind);

        $scores = array_column($this->stationScores($f), 'score');

        return [
            'inspections' => (int) ($row->inspections ?? 0),
            'entities'    => (int) ($row->entities ?? 0),
            'first_date'  => $row->first_date ?? null,
            'last_date'   => $row->last_date ?? null,
            'mean'        => $scores ? round(array_sum($scores) / count($scores), 1) : 0,
            'lowest'      => $scores ? min($scores) : 0,
            'highest'     => $scores ? max($scores) : 0,
            'below70'     => count(array_filter($scores, fn ($v) => $v < 70)),
            'compliant'   => count(array_filter($scores, fn ($v) => $v >= 70)),
        ];
    }

    public function stationScores(array $f = []): array
    {
        [$where, $bind] = $this->scope($f);

        return $this->db()->select("
            SELECT i.id, e.id AS entity_id, e.name, e.district, e.sector, e.owner, e.upi,
                   i.inspection_date, i.inspector_name,
                   ROUND(SUM(CASE WHEN ia.status='yes' THEN ci.max_score ELSE 0 END),1) AS score
            FROM inspections i
            JOIN entities e            ON e.id = i.entity_id
            JOIN inspection_answers ia ON ia.inspection_id = i.id
            JOIN checklist_items ci    ON ci.id = ia.item_id
            $where GROUP BY i.id ORDER BY score ASC", $bind);
    }

    public function byDistrict(array $f = []): array
    {
        [$where, $bind] = $this->scope($f);

        return $this->db()->select("
            SELECT e.district, COUNT(DISTINCT i.id) AS inspections,
                   ROUND(100*SUM(CASE WHEN ia.status='yes' THEN ci.max_score ELSE 0 END)
                     / NULLIF(SUM(CASE WHEN ia.status IN ('yes','no') THEN ci.max_score ELSE 0 END),0),1) AS compliance
            FROM inspections i
            JOIN entities e            ON e.id = i.entity_id
            JOIN inspection_answers ia ON ia.inspection_id = i.id
            JOIN checklist_items ci    ON ci.id = ia.item_id
            $where AND e.district IS NOT NULL AND e.district <> ''
            GROUP BY e.district ORDER BY compliance DESC", $bind);
    }

    public function bySection(array $f = []): array
    {
        [$where, $bind] = $this->scope($f);

        return $this->db()->select("
            SELECT cs.section_number, cs.title,
                   ROUND(100*SUM(ia.status='yes')/NULLIF(SUM(ia.status IN ('yes','no')),0)) AS compliance
            FROM inspection_answers ia
            JOIN checklist_items ci    ON ci.id = ia.item_id
            JOIN checklist_sections cs ON cs.id = ci.section_id
            JOIN inspections i         ON i.id = ia.inspection_id
            JOIN entities e            ON e.id = i.entity_id
            $where GROUP BY cs.id ORDER BY compliance ASC", $bind);
    }

    public function topFailures(array $f = [], int $limit = 10): array
    {
        [$where, $bind] = $this->scope($f);

        return $this->db()->select("
            SELECT ci.label, SUM(ia.status='no') AS failures, COUNT(*) AS assessed,
                   ROUND(100*SUM(ia.status='no')/COUNT(*)) AS fail_pct
            FROM inspection_answers ia
            JOIN checklist_items ci ON ci.id = ia.item_id
            JOIN inspections i      ON i.id = ia.inspection_id
            JOIN entities e         ON e.id = i.entity_id
            $where AND ia.status IN ('yes','no')
            GROUP BY ci.id HAVING failures > 0
            ORDER BY failures DESC, fail_pct DESC LIMIT " . (int) $limit, $bind);
    }

    public function byType(): array
    {
        return $this->db()->select("
            SELECT et.id, et.code, et.name, COUNT(DISTINCT i.id) AS inspections,
                   ROUND(100*SUM(CASE WHEN ia.status='yes' THEN ci.max_score ELSE 0 END)
                     / NULLIF(SUM(CASE WHEN ia.status IN ('yes','no') THEN ci.max_score ELSE 0 END),0),1) AS compliance
            FROM entity_types et
            LEFT JOIN entities e            ON e.entity_type_id = et.id
            LEFT JOIN inspections i         ON i.entity_id = e.id AND i.deleted_at IS NULL
            LEFT JOIN inspection_answers ia ON ia.inspection_id = i.id
            LEFT JOIN checklist_items ci    ON ci.id = ia.item_id
            GROUP BY et.id ORDER BY et.id");
    }

    public function distribution(array $f = []): array
    {
        $scores = array_column($this->stationScores($f), 'score');
        $bands  = ['Below 50%' => 0, '50 to 69%' => 0, '70 to 84%' => 0, '85% and above' => 0];

        foreach ($scores as $s) {
            if ($s < 50)     $bands['Below 50%']++;
            elseif ($s < 70) $bands['50 to 69%']++;
            elseif ($s < 85) $bands['70 to 84%']++;
            else             $bands['85% and above']++;
        }
        return $bands;
    }

    public function locations(array $f = []): array
    {
        [$where, $bind] = $this->scope(['type' => $f['type'] ?? null]);

        $col = fn (string $c) => array_column($this->db()->select("
            SELECT DISTINCT e.$c AS v FROM inspections i JOIN entities e ON e.id = i.entity_id
            $where AND e.$c IS NOT NULL AND e.$c <> '' ORDER BY e.$c", $bind), 'v');

        return ['districts' => $col('district'), 'sectors' => $col('sector'), 'cells' => $col('cell')];
    }

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

    /**
     * Universal search across the identifiers an officer would have.
     *
     * @param string   $term  Search term
     * @param int      $limit Max results
     * @param int|null $type  Optional entity_type_id to filter by
     * @return array
     */
    public function search(string $term, int $limit = 30, ?int $type = null): array
    {
        $like = '%' . $term . '%';
        $bind = [$like, $like, $like, $like, $like];

        $typeCondition = '';
        if ($type !== null) {
            $typeCondition = ' AND e.entity_type_id = ? ';
            $bind[] = $type;
        }

        return $this->db()->select("
            SELECT e.id AS entity_id, e.name, e.upi, e.owner, e.district, e.sector,
                   e.telephone, e.email, et.name AS type_name,
                   COUNT(DISTINCT i.id) AS inspection_count,
                   MAX(i.inspection_date) AS last_inspection
            FROM entities e
            JOIN entity_types et ON et.id = e.entity_type_id
            LEFT JOIN inspections i ON i.entity_id = e.id AND i.deleted_at IS NULL
            WHERE e.deleted_at IS NULL
              AND (e.name LIKE ? OR e.upi LIKE ? OR e.owner LIKE ?
                   OR e.email LIKE ? OR e.telephone LIKE ?)
              {$typeCondition}
            GROUP BY e.id ORDER BY e.name LIMIT " . (int) $limit,
            $bind);
    }

    /**
     * Helper to get the entity type ID from its code (if the code exists in the legacy table).
     */
    public function getTypeIdFromCode(string $code): ?int
    {
        $result = $this->db()->selectOne(
            'SELECT id FROM entity_types WHERE code = ? LIMIT 1',
            [$code]
        );
        return $result->id ?? null;
    }

    /** Compliance colour band, used consistently everywhere. */
    public static function band(float $pct): string
    {
        if ($pct >= 85) return 'good';
        if ($pct >= 70) return 'fair';
        if ($pct >= 50) return 'weak';
        return 'poor';
    }

    /** Deliberation, derived from the compliance level. */
    public static function deliberation(float $pct): array
    {
        if ($pct >= 85) return ['Compliant', 'good'];
        if ($pct >= 70) return ['Minor observations', 'fair'];
        if ($pct >= 50) return ['Corrective notice', 'weak'];
        return ['Enforcement action', 'poor'];
    }
}