<?php

namespace App\Http\Controllers;

use App\Services\InspectionStats;
use App\Support\ComplianceBand;
use App\Support\FiscalPeriod;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Drilling from a figure down to the premises behind it.
 *
 * A dashboard percentage tells a supervisor that something is wrong.
 * This tells an inspector which requirement is failing and, one step
 * further, which premises to visit. Both levels export.
 */
class AnalysisController extends Controller
{
    /**
     * Which checklist sections belong to which grouping.
     *
     * Siting failures are settled when a premises is built and cannot be
     * corrected by better housekeeping; operational failures usually can.
     * The distinction decides whether a letter asks for improvement or
     * for closure, which is why it is held explicitly.
     */
    private const GROUPS = [
        'petrol' => [
            'siting'     => ['1', '2', '3', '4', '5', '13'],
            'operations' => ['6', '7', '8', '9', '10', '11', '12', '14', '15'],
        ],
    ];

    private const GROUP_NAMES = [
        'siting'     => ['Siting & Design', 'Settled when the premises was built'],
        'operations' => ['Operations & Equipment', 'Dependent on day-to-day upkeep'],
        'all'        => ['All criteria', 'Every requirement on the checklist'],
    ];

    public function __construct(private InspectionStats $stats) {}

    /* ==============================================================
       Level one — the criteria within a grouping
       ============================================================== */
    public function group(Request $request, string $code, string $group = 'all')
    {
        $type    = InspectionTypeController::resolve($code);
        $filters = $this->filters($request, $code);

        $sections = self::GROUPS[$code][$group] ?? [];
        //$items    = $this->stats->itemsInSections($filters, $sections);
        
        $items = $this->stats->criteriaByPremises($filters, $sections);
        $items = array_map(function ($i) {
            $i->assessed = $i->premises_assessed;
            $i->passed   = $i->premises_passed;
            $i->failed   = $i->premises_failed;
            return $i;
        }, $items);
        

        [$name, $blurb] = self::GROUP_NAMES[$group] ?? self::GROUP_NAMES['all'];

        if ($request->query('export') === 'csv') {
            return $this->csvItems($items, $type, $name, $filters);
        }

        if ($request->query('export') === 'pdf') {
            return view('analysis.print-items', [
                'items' => $items, 'type' => $type, 'name' => $name,
                'scope' => $this->scopeLabel($request), 'period' => $this->periodLabel($request),
            ]);
        }

        $assessed = array_sum(array_map(fn ($i) => (int) $i->assessed, $items));
        $failed   = array_sum(array_map(fn ($i) => (int) $i->failed, $items));

        return view('analysis.group', [
            'type'    => $type,
            'group'   => $group,
            'name'    => $name,
            'blurb'   => $blurb,
            'items'   => $items,
            'scope'   => $this->scopeLabel($request),
            'period'  => $this->periodLabel($request),
            'query'   => $request->query(),
            'summary' => [
                'criteria'   => count($items),
                'assessed'   => $assessed,
                'failed'     => $failed,
                'compliance' => $assessed ? round(100 * ($assessed - $failed) / $assessed, 1) : 0,
                'worst'      => count($items)
                                    ? min(array_map(fn ($i) => (int) $i->compliance, $items))
                                    : 0,
            ],
        ]);
    }

    /* ==============================================================
       Level two — the premises failing one criterion
       ============================================================== */
    public function item(Request $request, string $code, int $itemId)
    {
        $type    = InspectionTypeController::resolve($code);
        $filters = $this->filters($request, $code);

        $item = $this->stats->item($itemId);
        abort_unless($item, 404, 'No such checklist item.');

        $failing = $this->stats->entitiesFailingItem($filters, $itemId, 'no');
        $passing = $this->stats->entitiesFailingItem($filters, $itemId, 'yes');

        if ($request->query('export') === 'csv') {
            return $this->csvEntities($failing, $type, $item, $filters);
        }

        if ($request->query('export') === 'pdf') {
            return view('analysis.print-entities', [
                'rows' => $failing, 'type' => $type, 'item' => $item,
                'scope' => $this->scopeLabel($request), 'period' => $this->periodLabel($request),
            ]);
        }

        $total = count($failing) + count($passing);

        return view('analysis.item', [
            'type'    => $type,
            'item'    => $item,
            'failing' => $failing,
            'scope'   => $this->scopeLabel($request),
            'period'  => $this->periodLabel($request),
            'query'   => $request->query(),
            'summary' => [
                'assessed'   => $total,
                'failed'     => count($failing),
                'passed'     => count($passing),
                'compliance' => $total ? round(100 * count($passing) / $total, 1) : 0,
            ],
        ]);
    }

    /* ==============================================================
       Shared
       ============================================================== */

    private function filters(Request $request, string $code): array
    {
        $user = auth()->user();

        $district = $user->isCityWide()
            ? $request->query('district')
            : $user->district?->name;

        [$from, $to] = FiscalPeriod::resolve(
            $request->query('period'),
            $request->query('from'),
            $request->query('to')
        );

        return array_filter([
            'type'     => $code,
            'district' => $district,
            'sector'   => $request->query('sector'),
            'cell'     => $request->query('cell'),
            'from'     => $from?->toDateString(),
            'to'       => $to?->toDateString(),
        ]);
    }

    private function scopeLabel(Request $request): string
    {
        $user = auth()->user();

        $parts = array_filter([
            $user->isCityWide() ? $request->query('district') : $user->district?->name,
            $request->query('sector'),
            $request->query('cell'),
        ]);

        return $parts ? implode(' · ', $parts) : 'City of Kigali';
    }

    private function periodLabel(Request $request): string
    {
        [, , $label] = FiscalPeriod::resolve(
            $request->query('period'),
            $request->query('from'),
            $request->query('to')
        );

        return $label;
    }

    /* ---------------- Exports ---------------- */

    private function csvItems(array $items, array $type, string $name, array $filters): StreamedResponse
    {
        $filename = 'criteria-' . $type['code'] . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($items, $type, $name) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['CITY OF KIGALI']);
            fputcsv($out, ['Directorate of Inspection']);
            fputcsv($out, [$type['name'] . ' — ' . $name]);
            fputcsv($out, ['Generated', now()->format('j F Y, H:i')]);
            fputcsv($out, []);

            fputcsv($out, ['Section', 'Requirement', 'Weight',
                           'Assessed', 'Compliant', 'Not compliant', 'N/A', 'Compliance %']);

            foreach ($items as $i) {
                fputcsv($out, [
                    $i->section_number . '. ' . $i->section_title,
                    rtrim($i->label, '. '),
                    rtrim(rtrim(number_format((float) $i->max_score, 2), '0'), '.'),
                    $i->assessed, $i->passed, $i->failed, $i->not_applicable,
                    $i->compliance,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvEntities(array $rows, array $type, object $item, array $filters): StreamedResponse
    {
        $filename = 'non-compliant-item-' . $item->id . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows, $type, $item) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['CITY OF KIGALI']);
            fputcsv($out, ['Directorate of Inspection']);
            fputcsv($out, [$type['name'] . ' — premises not complying']);
            fputcsv($out, ['Requirement', rtrim($item->label, '. ')]);
            fputcsv($out, ['Section', $item->section_number . '. ' . $item->section_title]);
            fputcsv($out, ['Generated', now()->format('j F Y, H:i')]);
            fputcsv($out, ['Premises listed', count($rows)]);
            fputcsv($out, []);

            fputcsv($out, ['#', 'Case reference', 'Premises', 'UPI', 'Owner', 'Telephone', 'Email',
                           'District', 'Sector', 'Cell', 'Inspected', 'Inspector',
                           'Overall compliance %', 'Consequence', 'Comment']);

            foreach ($rows as $n => $r) {
                fputcsv($out, [
                    $n + 1,
                    $r->case_reference ?: '',
                    $r->name,
                    $r->upi ?: '',
                    $r->owner ?: '',
                    $r->telephone ?: '',
                    $r->email ?: '',
                    $r->district ?: '',
                    $r->sector ?: '',
                    $r->cell ?: '',
                    $r->inspection_date,
                    $r->inspector_name,
                    number_format((float) $r->compliance, 2, '.', ''),
                    ComplianceBand::label((float) $r->compliance),
                    $r->comment ?: '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
