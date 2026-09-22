<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Inspection;
use App\Services\LegacyStats;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Premises record, inspection history and generated report.
 */
class EntityController extends Controller
{
    public function __construct(private LegacyStats $stats) {}

    /** Full record for one premises: history, latest checklist, report. */
        /**
     * Everything on one premises: its particulars, every visit, and the
     * most recent checklist.
     *
     * Read from this platform. It read the legacy staging database until
     * now, which was right while inspections were being migrated and
     * wrong the moment they started being recorded here — the list
     * offered premises the page could not open.
     */
    public function show(Entity $entity)
    {
        $inspections = $entity->inspections()
            ->with(['answers.item.section', 'team', 'documents'])
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->get();

        $latest = $inspections->first();

        return view('entity.show', [
            'entity'      => $entity->load('upis'),
            'inspections' => $inspections,
            'latest'      => $latest,

            /* Grouped by section, as the checklist is read. */
            /* Grouped by section, the shape the view reads: a number, a
               title, and the items beneath. LegacyStats assembled this in
               SQL; here it is built from the answers. */
            'checklist'   => $latest
                ? $latest->answers
                    ->filter(fn ($a) => $a->item && $a->item->section)
                    ->sortBy(fn ($a) => [
                        $a->item->section->sort_order ?? 0,
                        $a->item->sort_order ?? 0,
                      ])
                    ->groupBy(fn ($a) => $a->item->section->id)
                    ->map(fn ($rows) => [
                        'number' => $rows->first()->item->section->section_number,
                        'title'  => $rows->first()->item->section->title,

                        /* Scored with prohibition items understood: a
                           requirement met by the absence of something is
                           complied with by answering no. */
                        'earned'   => $rows->sum(fn ($a) => (
                            in_array($a->status, ['yes','no'], true)
                            && ($a->item->is_prohibition ? $a->status === 'no' : $a->status === 'yes')
                        ) ? (float) $a->item->max_score : 0),

                        'possible' => $rows->sum(fn ($a) => in_array($a->status, ['yes','no'], true)
                            ? (float) $a->item->max_score : 0),

                        'items'  => $rows->map(fn ($a) => (object) [
                            'label'          => $a->item->label,
                            'status'         => $a->status,
                            'comment'        => $a->comment,
                            'max_score'      => $a->item->max_score,
                            'is_prohibition' => (bool) $a->item->is_prohibition,
                        ])->values(),
                    ])
                    ->values()
                : collect(),

            'team'        => $latest?->team ?? collect(),

            /* The deliberation, from the same bands the dashboards use.
               LegacyStats had its own thresholds, so the same premises
               could read differently on two screens. */
            /* The card reads this directly. LegacyStats supplied it and
               the rewrite did not, so a premises scoring 34% showed 0. */
            'latestScore' => $latest ? round((float) $latest->compliance, 1) : 0,

            'verdict'     => $latest ? $latest->deliberation()[0] : '—',
            'tone'        => $latest ? $latest->deliberation()[1] : 'na',

            /* The tallies the view expects. LegacyStats computed these in
               SQL; here they are counted with prohibition items
               understood — a requirement met by the absence of something
               is complied with by answering no. */
            'counts'      => $latest
                ? $latest->answers->reduce(function ($c, $a) {
                      if (! $a->status) return $c;
                      if ($a->status === 'na') { $c['na']++; return $c; }
                      $ok = $a->item?->is_prohibition
                          ? $a->status === 'no'
                          : $a->status === 'yes';
                      $ok ? $c['yes']++ : $c['no']++;
                      return $c;
                  }, ['yes' => 0, 'no' => 0, 'na' => 0])
                : ['yes' => 0, 'no' => 0, 'na' => 0],
        ]);
    }
    /** The generated inspection report for one visit. */
    /**
     * The report for one visit.
     *
     * Redirected rather than rendered. This built a second report from
     * the legacy database — a different document from the one the
     * platform generates, missing the faults, the prohibition-aware
     * scoring and the Director who actually covers the premises.
     *
     * One inspection, one report.
     */
    public function report(int $entity, Inspection $inspection)
    {
        abort_unless($inspection->entity_id === $entity, 404,
            'That inspection does not belong to this premises.');

        return redirect()->route('inspection.show', $inspection);
    }

    /**
     * Opening a premises file: a new inspection, or a follow-up.
     *
     * The list of premises is filtered by the category code held on the
     * entity. It used to be filtered by a numeric type id looked up in
     * entity_types — a table that no longer exists — so only the two
     * categories carrying a legacy id in config were filtered at all.
     *
     * Ongoing construction also carries a stage, chosen on this page,
     * because substructure work is buried before superstructure begins
     * and the two are assessed against different checklists.
     */
    public function addFile(Request $request, string $code)
    {
        $type  = InspectionTypeController::resolve($code);
        $stage = $request->query('stage');

        /* entity_types no longer exists, so getTypeIdFromCode() threw
           for every category. The code itself is on every entity. */
        /* The premises list comes from the legacy database, which keys
           categories by a numeric id rather than by code. Only petrol and
           buildings have one — construction was never in the old platform,
           so its list is empty, which is correct. */
        $legacyId = $type['legacy_id'] ?? null;
        $filters  = array_filter(['type' => $legacyId]);
        $search = trim((string) $request->query('q', ''));

        return view('entity.add-file', [
            'type'     => $type,
            'stage'    => $stage,
            'search'   => $search,
            'existing' => app(\App\Services\InspectionStats::class)
                            ->premisesForFollowUp($code, $search),
        ]);
    }

    /**
     * The stages a category is inspected in, or an empty array where the
     * category has only one checklist.
     */
    private function stagesFor(string $code): array
    {
        $defined = [
            'construction' => [
                'substructure' => [
                    'label'   => 'Stage 1',
                    'heading' => 'Substructure',
                    'blurb'   => 'Foundation works — trenches, shoring, rebar, blinding and '
                               . 'concrete cover. Assessed before the pour, because none of '
                               . 'it can be verified afterwards.',
                ],
                'superstructure' => [
                    'label'   => 'Stage 2',
                    'heading' => 'Superstructure',
                    'blurb'   => 'Works above ground — walling, roof, scaffolding, protective '
                               . 'equipment and the documents and test reports held on site.',
                ],
            ],
        ];

        if (! isset($defined[$code])) {
            return [];
        }

        return collect($defined[$code])->map(function ($s, $key) use ($code) {
            $t = \App\Models\ChecklistTemplate::current($code, $key);

            return $s + [
                'template' => $t,
                'items'    => $t ? $t->sections->sum(fn ($sec) => $sec->items->count()) : 0,
            ];
        })->all();
    }

    /** Universal search across UPI, owner, name, email and telephone. */
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        return view('entity.search', [
            'term'    => $term,
            'results' => $term !== '' ? $this->stats->search($term, 50) : [],
        ]);
    }
}