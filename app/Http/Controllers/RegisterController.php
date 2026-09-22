<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Inspection;
use App\Support\ComplianceBand;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The inspection register.
 *
 * One row per premises, showing how many times it has been inspected
 * and the outcome of the most recent visit. Reads the native tables,
 * not the legacy database. Completed inspections only — a draft has no
 * finalised score, and counting it would put a part-computed figure on
 * the register.
 *
 * There are exactly four deliberations, everywhere in this file and
 * everywhere else on the platform:
 *
 *   Permanent closure     a siting requirement is not met, whatever the score
 *   Temporary closure     0 to 49.99%, no siting violation
 *   Improvement + fine    50 to 97.99%, no siting violation
 *   Compliant             98% and above, no siting violation
 */
class RegisterController extends Controller
{
    public function index(Request $request, string $code)
    {
        $type = InspectionTypeController::resolve($code);

        $query = $this->build($request, $code);
        $rows  = $query->get();

        /* ==============================================================
           WASTEWATER ENRICHMENT – add the 17 columns
           ============================================================== */
        if ($code === 'waste_water') {
            $inspectionIds = $rows->pluck('last_inspection')->filter()->unique()->values()->toArray();

            if (!empty($inspectionIds)) {
                $inspections = Inspection::with(['entity', 'wastewaterInspection'])
                    ->whereIn('id', $inspectionIds)
                    ->get()
                    ->keyBy('id');

                $rows = $rows->map(function ($entity) use ($inspections) {
                    $inspection = $inspections->get($entity->last_inspection);
                    $ww = $inspection ? $inspection->wastewaterInspection : null;

                    // Map Excel columns – add as properties on the entity
                    $entity->building_use   = $entity->use_type ?? null;
                    $entity->manager_rep    = $inspection ? $inspection->manager_name : null;
                    $entity->building_name  = $entity->name ?? null;
                    $entity->ww_type        = $inspection ? $inspection->wastewater_system_type : null;
                    $entity->ww_status      = $inspection ? $inspection->wastewater_compliance_status : null;
                    $entity->maintenance_co = $ww && !empty($ww->maintenance_company) ? 'Yes' : 'No';
                    $entity->test_results   = $ww && $ww->effluent_test_available ? 'Yes' : 'No';
                    $entity->observations   = $inspection ? $inspection->observations : null;
                    $entity->recommendation = $inspection ? $inspection->recommendations : null;
                    $entity->decision       = $inspection ? $inspection->decision_taken : null;
                    $entity->timeline       = $inspection ? $inspection->created_at->format('d/m/Y') : null;

                    // Ensure district/sector/cell/owner are set
                    $entity->district = $entity->district ?? null;
                    $entity->sector   = $entity->sector ?? null;
                    $entity->cell     = $entity->cell ?? null;
                    $entity->owner    = $entity->owner ?? null;

                    return $entity;
                });
            }
        }

        if ($export = $request->query('export')) {
            return $this->export($export, $rows, $type, $this->describeFilters($request));
        }
        
      // Get all sectors and cells for the dropdowns
            $allSectors = Entity::where('type_code', $code)
                ->whereNotNull('district')
                ->whereNotNull('sector')
                ->distinct()
                ->orderBy('district')
                ->orderBy('sector')
                ->get(['district', 'sector'])
                ->groupBy('district')
                ->map(fn ($items) => $items->pluck('sector')->unique()->values()->toArray())
                ->toArray();
            
            $allCells = Entity::where('type_code', $code)
                ->whereNotNull('district')
                ->whereNotNull('sector')
                ->whereNotNull('cell')
                ->distinct()
                ->orderBy('district')
                ->orderBy('sector')
                ->orderBy('cell')
                ->get(['district', 'sector', 'cell'])
                ->groupBy('district')
                ->map(fn ($items) => $items->groupBy('sector')->map(fn ($cells) => $cells->pluck('cell')->unique()->values()->toArray())->toArray())
                ->toArray();
        

        return view('register.index', [
            'type'       => $type,
            'rows'       => $rows,
            'filters'    => $request->only([
                'upi', 'name', 'owner', 'district', 'sector', 'cell',
                'date_from', 'date_to', 'band', 'visits',
            ]),
            'districts'  => Entity::where('type_code', $code)->whereNotNull('district')
                                  ->distinct()->orderBy('district')->pluck('district'),
            'summary'    => $this->summarise($rows),
        'allSectors' => $allSectors,
        'allCells'   => $allCells,
            
        ]);
    }

    /* ------------------------------------------------------------ */

    private function build(Request $request, string $code)
    {
        $latest = fn (string $column) => Inspection::select($column)
            ->whereColumn('entity_id', 'entities.id')
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->orderByDesc('inspection_date')->orderByDesc('id')
            ->limit(1);

        $query = Entity::query()
            ->where('type_code', $code)
            ->whereHas('inspections', fn ($q) => $q->where('status', 'completed'))
            ->withCount(['inspections' => fn ($q) => $q->whereNull('deleted_at')->where('status', 'completed')])
            ->addSelect('entities.*')
            ->addSelect(['last_date'       => $latest('inspection_date')])
            ->addSelect(['compliance'      => $latest('compliance')])
            ->addSelect(['last_inspection' => $latest('id')])
            ->addSelect(['inspector'       => $latest('inspector_name')]);

        /* Whether the entity's own latest completed inspection failed a
           siting requirement — same items as Inspection::band() uses on
           a single inspection's own page, referenced from that one
           constant so this cannot drift from it. Only meaningful for
           petrol; for every other category this is simply always 0. */
        if ($code === 'petrol') {
            $itemIds = implode(',', Inspection::PETROL_SITING_ITEMS);
            $wetlandCodes = "'" . implode("','", Inspection::PETROL_WETLAND_ZONING_CODES) . "'";

            $query->addSelect(['siting_violation' => Inspection::selectRaw(
                    "EXISTS (
                        SELECT 1 FROM inspection_answers ia
                        WHERE ia.inspection_id = inspections.id
                        AND ia.item_id IN ({$itemIds})
                        AND ia.status = 'no'
                    ) OR entities.zoning IN ({$wetlandCodes})"
                )
                ->whereColumn('entity_id', 'entities.id')
                ->whereNull('deleted_at')
                ->where('status', 'completed')
                ->orderByDesc('inspection_date')->orderByDesc('id')
                ->limit(1)
            ]);
        } else {
            $query->addSelect(['siting_violation' => Inspection::selectRaw('0')->limit(1)]);
        }

        if ($upi = trim((string) $request->query('upi'))) {
            $query->where('upi', 'like', "%{$upi}%");
        }

        if ($name = trim((string) $request->query('name'))) {
            $query->where('name', 'like', "%{$name}%");
        }

        if ($owner = trim((string) $request->query('owner'))) {
            $query->where('owner', 'like', "%{$owner}%");
        }

        if ($district = $request->query('district')) {
            $query->where('district', $district);
        }

        if ($sector = trim((string) $request->query('sector'))) {
            $query->where('sector', 'like', "%{$sector}%");
        }

        if ($cell = trim((string) $request->query('cell'))) {
            $query->where('cell', 'like', "%{$cell}%");
        }

        if ($from = $request->query('date_from')) {
            $query->whereHas('inspections', fn ($q) => $q->whereDate('inspection_date', '>=', $from));
        }

        if ($to = $request->query('date_to')) {
            $query->whereHas('inspections', fn ($q) => $q->whereDate('inspection_date', '<=', $to));
        }

        if ($visits = $request->query('visits')) {
            if ($visits === 'once') {
                $query->having('inspections_count', '=', 1);
            } elseif ($visits === 'repeat') {
                $query->having('inspections_count', '>', 1);
            } elseif (is_numeric($visits)) {
                $query->having('inspections_count', '>=', (int) $visits);
            }
        }

        /* The one filter for deliberation. A siting violation always
           wins — a station scoring 90% with the wrong plot size is
           permanently closed, not merely due an improvement notice, so
           it appears only under 'permanent' and nowhere else. */
        if ($band = $request->query('band')) {
            $query->when($band === 'compliant',
                fn ($q) => $q->havingRaw('compliance >= 98 AND siting_violation = 0'));
            $query->when($band === 'uncompliant',
                fn ($q) => $q->havingRaw('compliance < 98 OR siting_violation = 1'));
            $query->when($band === 'permanent',
                fn ($q) => $q->havingRaw('siting_violation = 1'));
            $query->when($band === 'temporary',
                fn ($q) => $q->havingRaw('compliance BETWEEN 0 AND 49.99 AND siting_violation = 0'));
            $query->when($band === 'improve',
                fn ($q) => $q->havingRaw('compliance BETWEEN 50 AND 97.99 AND siting_violation = 0'));
        }

        return $query->orderBy('compliance');
    }

    /**
     * 'below70' is kept for the view's existing stat card, but it no
     * longer lines up with a real deliberation — Temporary and Improve
     * split at 50%, not 70%, since today's redefinition, so a figure
     * cut at 70% now spans part of both. Left in place so the page does
     * not error; worth replacing with something that means what the
     * page actually shows now, such as the live 'permanent' count
     * already available in 'tally'.
     */
    private function summarise($rows): array
    {
        $scores = $rows->pluck('compliance')->filter()->map(fn ($v) => (float) $v);

        return [
            'premises'    => $rows->count(),
            'inspections' => $rows->sum('inspections_count'),
            'mean'        => $scores->count() ? round($scores->avg(), 1) : 0,
            'repeat'      => $rows->where('inspections_count', '>', 1)->count(),
            'below70'     => $scores->filter(fn ($v) => $v < 70)->count(),
            'tally'       => ComplianceBand::tallyRows(
                $rows,
                fn ($r) => (float) $r->compliance,
                fn ($r) => (bool) $r->siting_violation
            ),
        ];
    }

    private function describeFilters(Request $request): array
    {
        $out = [];

        if ($v = $request->query('upi'))      $out['UPI contains'] = $v;
        if ($v = $request->query('name'))     $out['Name contains'] = $v;
        if ($v = $request->query('owner'))    $out['Owner contains'] = $v;
        if ($v = $request->query('district')) $out['District'] = $v;
        if ($v = $request->query('sector'))   $out['Sector contains'] = $v;
        if ($v = $request->query('cell'))     $out['Cell contains'] = $v;
        if ($v = $request->query('date_from'))$out['Inspected from'] = $v;
        if ($v = $request->query('date_to'))  $out['Inspected to'] = $v;

        if ($v = $request->query('band')) {
            $out['Deliberation'] = ComplianceBand::BANDS[$v]['label'] ?? $v;
        }

        if ($v = $request->query('visits')) {
            $out['Inspections'] = match ($v) {
                'once'   => 'Inspected once only',
                'repeat' => 'Inspected more than once',
                default  => "At least {$v}",
            };
        }

        return $out;
    }

    /* ------------------------------------------------------------
       Export — the Report column is deliberately omitted
       ------------------------------------------------------------ */

    private function export(string $format, $rows, array $type, array $filters)
    {
        abort_unless(auth()->user()->can('export.excel') || auth()->user()->can('export.pdf'), 403);

        if ($format === 'pdf') {
            return view('register.print', compact('rows', 'type', 'filters'));
        }

        return $this->csv($rows, $type, $filters);
    }

    private function csv($rows, array $type, array $filters): StreamedResponse
    {
        $filename = 'inspection-register-' . $type['code'] . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows, $type, $filters) {
            $out = fopen('php://output', 'w');

            // BOM so Excel reads UTF-8 correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['CITY OF KIGALI']);
            fputcsv($out, ['Directorate of Inspection']);
            fputcsv($out, [$type['name'] . ' — Inspection Register']);
            fputcsv($out, ['Generated', now()->format('j F Y, H:i')]);

            foreach ($filters as $label => $value) {
                fputcsv($out, [$label, $value]);
            }

            fputcsv($out, ['Premises listed', $rows->count()]);
            fputcsv($out, []);

            fputcsv($out, [
                '#', 'UPI', 'Zoning', 'Owner', $type['name'] . ' Name',
                'District', 'Sector', 'Date of Inspection',
                'Compliance (%)', 'Inspections', 'Deliberation',
            ]);

            foreach ($rows as $n => $r) {
                fputcsv($out, [
                    $n + 1,
                    $r->upi ?: '',
                    $r->zoning ?: '',
                    $r->owner ?: '',
                    $r->name,
                    $r->district ?: '',
                    $r->sector ?: '',
                    $r->last_date,
                    number_format((float) $r->compliance, 2, '.', ''),
                    $r->inspections_count,
                    self::deliberationFor((float) $r->compliance, (bool) $r->siting_violation)[0],
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * The same real four deliberations, for a single row. A siting
     * violation always wins, exactly as it does everywhere else on the
     * platform. The $sitingViolation parameter defaults to false only
     * so an old call site does not fatal — every real call should pass
     * the row's actual flag; the register's own table does, since it
     * has $r->siting_violation available on every row already.
     */
    public static function deliberationFor(float $pct, bool $sitingViolation = false): array
    {
        if ($sitingViolation) {
            $band = ComplianceBand::permanent();
            return [$band['label'], $band['tone']];
        }

        $band = ComplianceBand::for($pct);
        return [$band['label'], $band['tone']];
    }
}