<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Services\InspectionStats;
use App\Support\ComplianceBand;
use App\Support\FiscalPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One dashboard per inspection category.
 *
 * Everything on this screen counts premises, not inspections. A station
 * visited three times appears once, judged on where it stands now. The
 * alternative weights the picture by how often somewhere happened to be
 * revisited, which is not a measure of anything.
 */
class InspectionTypeController extends Controller
{
    public function __construct(private InspectionStats $stats) {}

    /**
     * Why each currently-flagged petrol station is flagged – per-reason
     * assessed/failed counts, and how many stations fail more than one
     * reason at once.
     */
    private function petrolSitingBreakdown($premises): array
    {
        $wetlandCodes = \App\Models\Inspection::PETROL_WETLAND_ZONING_CODES;
        $itemLabels = [
            51 => 'Plot size',
            55 => 'Power line clearance',
            56 => 'Distance from sensitive areas',
        ];
        $itemColours = [51 => '#B71C1C', 55 => '#E65100', 56 => '#6A1B9A'];

        $inspectionIds = $premises->pluck('inspection_id');

        $answers = \DB::table('inspection_answers')
            ->whereIn('inspection_id', $inspectionIds)
            ->whereIn('item_id', [51, 55, 56])
            ->whereIn('status', ['yes', 'no'])
            ->select('inspection_id', 'item_id', 'status')
            ->get()
            ->groupBy('item_id');

        $bars = [];
        $reasonsPerStation = [];

        foreach ($itemLabels as $itemId => $label) {
            $itemAnswers = $answers->get($itemId, collect());
            $assessed = $itemAnswers->count();
            $failed   = $itemAnswers->where('status', 'no')->count();

            foreach ($itemAnswers->where('status', 'no') as $a) {
                $reasonsPerStation[$a->inspection_id] = ($reasonsPerStation[$a->inspection_id] ?? 0) + 1;
            }

            $bars[] = [
                'label'    => $label,
                'assessed' => $assessed,
                'failed'   => $failed,
                'rate'     => $assessed ? round(100 * $failed / $assessed) : 0,
                'colour'   => $itemColours[$itemId],
            ];
        }

        /* Wetland is not an inspection answer – "assessed" is every
           petrol premises in scope, "failed" is however many are zoned
           this way. */
        $wetlandCount = 0;
        foreach ($premises as $p) {
            $entity = \App\Models\Entity::find($p->entity_id);
            if (in_array($entity?->zoning, $wetlandCodes, true)) {
                $wetlandCount++;
                $reasonsPerStation[$p->inspection_id] = ($reasonsPerStation[$p->inspection_id] ?? 0) + 1;
            }
        }

        array_unshift($bars, [
            'label'    => 'Wetland zoning',
            'assessed' => $premises->count(),
            'failed'   => $wetlandCount,
            'rate'     => $premises->count() ? round(100 * $wetlandCount / $premises->count()) : 0,
            'colour'   => '#1565C0',
        ]);

        $max = max(1, ...array_column($bars, 'failed'));
        foreach ($bars as &$b) {
            $b['pct'] = round(100 * $b['failed'] / $max);
        }
        unset($b);

        /* How many reasons each flagged station fails at once – 1
           through 4. A station appearing here failed at least one
           reason; it cannot fail zero, since that is what "flagged"
           means. */
        $combinations = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        foreach ($reasonsPerStation as $count) {
            if ($count >= 1 && $count <= 4) {
                $combinations[$count]++;
            }
        }

        return [
            'bars'         => $bars,
            'combinations' => $combinations,
        ];
    }

    public static function resolve(string $code): array
    {
        foreach (config('inspection_types.groups') as $group) {
            if (isset($group['types'][$code])) {
                return $group['types'][$code] + ['code' => $code, 'group' => $group['label']];
            }
        }

        throw new NotFoundHttpException("Unknown inspection type [$code]");
    }

    public function show(Request $request, string $code)
    {
        $type = self::resolve($code);
        $user = auth()->user();

        abort_unless($user->can('inspection.view.own'), 403);

        if (! $type['live']) {
            return view('type-planned', compact('type'));
        }

        /* A Director sees only their own district, whatever the URL says. */
        $district = $user->isCityWide()
            ? $request->query('district')
            : $user->district?->name;

        $sector = $request->query('sector');
        $cell   = $request->query('cell');

        [$from, $to, $periodLabel] = FiscalPeriod::resolve(
            $request->query('period'),
            $request->query('from'),
            $request->query('to')
        );

        $filters = array_filter([
            'type'     => $code,
            'district' => $district,
            'sector'   => $sector,
            'cell'     => $cell,
            'from'     => $from?->toDateString(),
            'to'       => $to?->toDateString(),
        ]);

        /* ---- One premises, one row ---- */
        $premises = collect($this->stats->latestPerPremises($filters));
        $scores   = $premises->pluck('compliance')->map(fn ($v) => (float) $v);

        /* Total visits, so frequency and repeat counts are honest. */
        $visits = Inspection::query()
            ->where('type_code', $code)
            ->where('status', 'completed')
            ->whereIn('entity_id', $premises->pluck('entity_id'))
            ->when($from, fn ($q) => $q->whereDate('inspection_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('inspection_date', '<=', $to))
            ->selectRaw('entity_id, COUNT(*) AS n')
            ->groupBy('entity_id')
            ->pluck('n', 'entity_id');

        /* Compliant means both a qualifying score and no siting violation */
        $compliant = $premises->filter(
            fn ($p) => ComplianceBand::isCompliant((float) $p->compliance)
                       && ! ($p->has_siting_violation ?? false)
        )->count();

        /* ---- Criteria, worst first, counted by premises ---- */
        $criteria = collect($this->stats->criteriaByPremises($filters))
            ->sortByDesc('premises_failed')
            ->values();

        /* Which two upper panels this category shows. */
        $panels = config('inspection_types.panels.' . $code, ['districts', 'ranking']);

        /* The district panel shows districts until one is chosen, then
           the sectors within it. */
        $byArea = $district
            ? $this->stats->bySector($filters)
            : $this->stats->districtsByPremises($filters);

        /* ==============================================================
           SUMMARY, DISTRICTS, AND ROWS (with wastewater enrichment)
           ============================================================== */
        $summary = [
            'premises'    => $premises->count(),
            'inspections' => $visits->sum(),
            'mean'        => $premises->count() ? round($premises->avg('compliance'), 1) : 0,
            'repeat'      => $visits->filter(fn ($n) => $n > 1)->count(),
            'below70'     => $premises->filter(fn ($p) => (float) $p->compliance < 70)->count(),
        ];

        $districts = \App\Models\Entity::distinct()->pluck('district')->filter()->values()->toArray();

        // Start with the base collection
        $rows = $premises;

        /* ---- WASTEWATER ENRICHMENT ---- */
        if ($code === 'waste_water') {
            $inspectionIds = $premises->pluck('inspection_id')->filter()->unique()->values()->toArray();

            if (!empty($inspectionIds)) {
                $inspections = \App\Models\Inspection::with(['entity', 'wastewaterInspection'])
                    ->whereIn('id', $inspectionIds)
                    ->get()
                    ->keyBy('id');

                $rows = $premises->map(function ($p) use ($inspections) {
                    $inspection = $inspections->get($p->inspection_id);
                    $entity = $inspection ? $inspection->entity : null;
                    $ww = $inspection ? $inspection->wastewaterInspection : null;

                    // Map Excel columns
                    $p->building_use   = $entity ? $entity->use_type : null;
                    $p->manager_rep    = $inspection ? $inspection->manager_name : null;
                    $p->building_name  = $entity ? $entity->name : ($p->name ?? null);
                    $p->ww_type        = $inspection ? $inspection->wastewater_system_type : null;
                    $p->ww_status      = $inspection ? $inspection->wastewater_compliance_status : null;
                    $p->maintenance_co = $ww && !empty($ww->maintenance_company) ? 'Yes' : 'No';
                    $p->test_results   = $ww && $ww->effluent_test_available ? 'Yes' : 'No';
                    $p->observations   = $inspection ? $inspection->observations : null;
                    $p->recommendation = $inspection ? $inspection->recommendations : null;
                    $p->decision       = $inspection ? $inspection->decision_taken : null;
                    $p->timeline       = $inspection ? $inspection->created_at->format('d/m/Y') : null;

                    // Ensure district/sector/cell/owner come from the entity
                    if ($entity) {
                        $p->district = $entity->district ?? $p->district ?? null;
                        $p->sector   = $entity->sector   ?? $p->sector   ?? null;
                        $p->cell     = $entity->cell     ?? $p->cell     ?? null;
                        $p->owner    = $entity->owner    ?? $p->owner    ?? null;
                    }

                    return $p;
                });
            }
        }

        /* ==============================================================
           HANDLE CSV / PDF EXPORT FOR WASTEWATER
           ============================================================== */
        if ($code === 'waste_water' && $request->has('export')) {
            $exportType = $request->query('export');

            $exportData = $rows->map(function ($row, $index) {
                return [
                    'N0'                         => $index + 1,
                    'District'                   => $row->district ?? '',
                    'Sector'                     => $row->sector ?? '',
                    'Cell'                       => $row->cell ?? '',
                    'Name of the Building'       => $row->building_name ?? $row->name ?? '',
                    'Building use'               => $row->building_use ?? '',
                    'Manager/ Representative'    => $row->manager_rep ?? '',
                    'Owner'                      => $row->owner ?? '',
                    'Type of waste water management' => $row->ww_type ?? '',
                    'Status'                     => $row->ww_status ?? '',
                    'Maintenance Company'        => $row->maintenance_co ?? '',
                    'Valid test results'         => $row->test_results ?? '',
                    'Observations'               => $row->observations ?? '',
                    'Recommendation'             => $row->recommendation ?? '',
                    'Compliance Rate'            => $row->compliance ?? 0,
                    'Decision'                   => $row->decision ?? '',
                    'Timeline'                   => $row->timeline ?? '',
                ];
            })->toArray();

            if ($exportType === 'csv') {
                return $this->exportCsv($exportData, 'wastewater_report.csv');
            }

            if ($exportType === 'pdf') {
                return $this->exportPdf($exportData, 'wastewater_report');
            }
        }

        /* ==============================================================
           RETURN VIEW – ALWAYS 'type' FOR ALL INSPECTION TYPES
           ============================================================== */
        return view('type', [
            'type'       => $type,
            'cityWide'   => $user->isCityWide(),
            'district'   => $user->district?->name,
            'scopeName'  => $this->scopeName($district, $sector, $cell),
            'canExport'  => $user->can('export.excel') || $user->can('export.pdf'),

            'filters' => [
                'district' => $district,
                'sector'   => $sector,
                'cell'     => $cell,
                'period'   => $request->query('period'),
                'from'     => $request->query('from'),
                'to'       => $request->query('to'),
            ],
            'periodLabel'   => $periodLabel,
            'periodOptions' => FiscalPeriod::options($this->earliestYear($code)),

            // ---- NEW VARIABLES FOR THE REGISTER TABLE ----
            'rows'      => $rows,
            'summary'   => $summary,
            'districts' => $districts,

            // ---- KEEP EXISTING ONES (unchanged) ----
            'figures' => [
                'premises'    => $premises->count(),
                'inspections' => (int) $visits->sum(),
                'frequency'   => $premises->count() ? round($visits->sum() / $premises->count(), 1) : 0,
                'repeat'      => $visits->filter(fn ($n) => $n > 1)->count(),
                'compliant'   => $compliant,
                'uncompliant' => $premises->count() - $compliant,
                'mean'        => $premises->count() ? round($premises->avg('compliance'), 1) : 0,
            ],

            'panels'       => $panels,
            'byArea'       => $byArea,
            'areaLevel'    => $district ? 'sector' : 'district',
            'records'      => $premises->take(30),
            'recordCount'  => $premises->count(),
            'permanentStations' => $code === 'petrol'
                ? $premises->filter(fn ($p) => $p->has_siting_violation ?? false)
                    ->sortByDesc('compliance')->values()
                : collect(),
            'permanentBreakdown' => $code === 'petrol'
                ? $this->petrolSitingBreakdown($premises)
                : [],
            'tally'    => ComplianceBand::tallyRows(
                $premises,
                fn ($p) => (float) $p->compliance,
                fn ($p) => $p->has_siting_violation ?? false
            ),
            'gap'      => $this->stats->sitingVsOperationsByPremises($filters, $code),
            'mapped'   => $premises->filter(fn ($p) => $p->latitude && $p->longitude)->values()->all(),
            'sections' => $this->stats->sectionsByPremises($filters),
            'criteria' => $criteria->take(12)->all(),
        ]);
    }

    /* ============================================================== */

    /**
     * Export data as CSV
     */
    private function exportCsv(array $data, string $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $handle = fopen('php://output', 'w');
            if (!empty($data)) {
                fputcsv($handle, array_keys($data[0]));
            }
            foreach ($data as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export data as PDF
     * Requires: composer require barryvdh/laravel-dompdf
     */
    private function exportPdf(array $data, string $title)
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.wastewater_pdf', [
            'data' => $data,
            'title' => $title,
        ]);
        return $pdf->download('wastewater_report.pdf');
    }

    private function scopeName(?string $district, ?string $sector, ?string $cell): string
    {
        $parts = array_filter([$district, $sector, $cell]);

        return $parts ? implode(' · ', $parts) : 'City of Kigali';
    }

    private function earliestYear(string $code): int
    {
        $first = Inspection::where('type_code', $code)->min('inspection_date');

        return $first
            ? FiscalPeriod::yearOf(Carbon::parse($first))
            : FiscalPeriod::yearOf();
    }
}