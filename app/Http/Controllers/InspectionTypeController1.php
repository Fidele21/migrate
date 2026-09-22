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
     * How many currently-flagged petrol stations fail each specific
     * siting reason. Independent counts, not exclusive categories ！ one
     * station can appear in more than one bar.
     */
        /**
     * Why each currently-flagged petrol station is flagged ！ per-reason
     * assessed/failed counts, and how many stations fail more than one
     * reason at once. A station can fail several reasons simultaneously,
     * so the bars are independent counts, not slices of one whole.
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

        /* Wetland is not an inspection answer ！ "assessed" is every
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

        /* How many reasons each flagged station fails at once ！ 1
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
        /* Visits within the same window as everything else, so the
           frequency figure and the period agree. */
        $visits = Inspection::query()
            ->where('type_code', $code)
            ->where('status', 'completed')
            ->whereIn('entity_id', $premises->pluck('entity_id'))
            ->when($from, fn ($q) => $q->whereDate('inspection_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('inspection_date', '<=', $to))
            ->selectRaw('entity_id, COUNT(*) AS n')
            ->groupBy('entity_id')
            ->pluck('n', 'entity_id');

               /* Compliant means both a qualifying score and no siting
           violation ！ a station at 99% that is sited wrongly is not
           compliant merely because its checklist score is high. */
        $compliant = $premises->filter(
            fn ($p) => ComplianceBand::isCompliant((float) $p->compliance)
                       && ! ($p->has_siting_violation ?? false)
        )->count();

        /* ---- Criteria, worst first, counted by premises ---- */
        $criteria = collect($this->stats->criteriaByPremises($filters))
            ->sortByDesc('premises_failed')
            ->values();
            
        /* Which two upper panels this category shows. A category not
           listed gets districts and ranking, which suit anything. */
        $panels = config('inspection_types.panels.' . $code, ['districts', 'ranking']);

        /* The district panel shows districts until one is chosen, then
           the sectors within it - the same chart answering "which
           district is worst" and "where in Gasabo". */
        $byArea = $district
            ? $this->stats->bySector($filters)
            : $this->stats->districtsByPremises($filters);

        return view('type', [
            'type'      => $type,
            'cityWide'  => $user->isCityWide(),
            'district'  => $user->district?->name,
            'scopeName' => $this->scopeName($district, $sector, $cell),
            'canExport' => $user->can('export.excel') || $user->can('export.pdf'),

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

            'figures' => [
                'premises'    => $premises->count(),
                'inspections' => (int) $visits->sum(),
                'frequency'   => $premises->count()
                                    ? round($visits->sum() / $premises->count(), 1)
                                    : 0,
                'repeat'      => $visits->filter(fn ($n) => $n > 1)->count(),
                'compliant'   => $compliant,
                'uncompliant' => $premises->count() - $compliant,
                'mean'        => $scores->count() ? round($scores->avg(), 1) : 0,
            ],

            'panels'       => $panels,
            'byArea'       => $byArea,
            'areaLevel'    => $district ? 'sector' : 'district',
            'records'      => $premises->take(30),
            'recordCount'  => $premises->count(),
            
                        /* Every flagged station, uncapped ！ 'records' above is
               limited to 30 for the general table, which could silently
               drop a permanently-closed station if it happened to sit
               past that cut. Permanent closure has to be complete, not
               a sample. */
                'permanentStations' => $code === 'petrol'
                ? $premises->filter(fn ($p) => $p->has_siting_violation ?? false)
                    ->sortByDesc('compliance')->values()
                : collect(),
                
                

            /* Why each flagged station is flagged ！ a station can fail
               more than one reason at once (wetland zoning and power
               line clearance, say), so these counts are independent,
               not slices of one whole. A pie chart would misrepresent
               that; a bar per reason does not. */
                        'permanentStations' => $code === 'petrol'
                ? $premises->filter(fn ($p) => $p->has_siting_violation ?? false)
                    ->sortByDesc('compliance')->values()
                : collect(),

            /* Why each flagged station is flagged ！ a station can fail
               more than one reason at once (wetland zoning and power
               line clearance, say), so these counts are independent,
               not slices of one whole. */
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

    private function scopeName(?string $district, ?string $sector, ?string $cell): string
    {
        $parts = array_filter([$district, $sector, $cell]);

        return $parts ? implode(' 揃 ', $parts) : 'City of Kigali';
    }

    private function earliestYear(string $code): int
    {
        $first = Inspection::where('type_code', $code)->min('inspection_date');

        return $first
            ? FiscalPeriod::yearOf(Carbon::parse($first))
            : FiscalPeriod::yearOf();
    }
}