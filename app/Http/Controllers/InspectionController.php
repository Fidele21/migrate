<?php

namespace App\Http\Controllers;

use App\Models\ChecklistTemplate;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Entity;
use App\Models\EntityUpi;
use App\Models\Inspection;
use App\Models\InspectionAnswer;
use App\Models\InspectionPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recording, editing and issuing inspections.
 *
 * A draft may be saved part-finished — an inspector often has to stop
 * and return. Completing an inspection requires every checklist item to
 * carry an answer, because an unanswered item is excluded from both the
 * earned and the possible total, which shifts the compliance percentage
 * rather than simply lowering it.
 */
class InspectionController extends Controller
{
    private const MAX_PHOTOS = 3;

    /* ==============================================================
       Drafts
       ============================================================== */

    /**
     * Inspections not yet completed.
     *
     * An inspector sees their own; anyone with district or city-wide
     * viewing rights sees the drafts within their scope, so a supervisor
     * can tell what is outstanding.
     */
    public function drafts(Request $request)
    {
        $user = auth()->user();

        $query = Inspection::with(['entity', 'inspector'])
            ->where('status', 'draft')
            ->orderByDesc('updated_at');

        if ($user->can('inspection.view.all')) {
            // everything
        } elseif ($user->can('inspection.view.district')) {
            $query->where('district_id', $user->district_id);
        } else {
            $query->where('inspector_id', $user->id);
        }

        if ($code = $request->query('type')) {
            $query->where('type_code', $code);
        }

        return view('inspection.drafts', [
            'drafts' => $query->get(),
            'scope'  => $user->can('inspection.view.all')
                ? 'All districts'
                : ($user->can('inspection.view.district')
                    ? ($user->district?->name ?? 'Your district')
                    : 'Your inspections'),
        ]);
    }

    /* ==============================================================
       Showing the form
       ============================================================== */

    public function create(Request $request, string $code)
    {
        $this->authorizeAbility('inspection.create');

        $type  = InspectionTypeController::resolve($code);
        $stage = $request->input('stage');

                /* Only two categories are inspected in more than one way. */
        if (! in_array($code, ['construction', 'desk_review', 'waste_water'], true)) {
            $stage = null;
        }
        /* Construction carries both, because the officer chooses the
           stage on the form and reloading would discard whatever had
           already been answered. */
                /* Construction and wastewater each carry two, because the
           officer chooses which one applies on the form and reloading
           would discard whatever had already been answered. */
        $templates = match ($code) {
            'construction' => [
                'Substructure'   => ChecklistTemplate::current($code, 'substructure'),
                'Superstructure' => ChecklistTemplate::current($code, 'superstructure'),
            ],
            'waste_water' => [
                'stp'         => ChecklistTemplate::current($code, 'stp'),
                'septic_tank' => ChecklistTemplate::current($code, 'septic_tank'),
            ],
            default => ['' => ChecklistTemplate::current($code, $stage)],
        };

        $templates = array_filter($templates);
        $template  = collect($templates)->first();

        /* Desk review opens with no checklist by design — the kind of
           application is chosen on the form itself, and only then does
           a checklist exist to show. Every other category needs a real,
           already-decided template before the form means anything;
           desk review is the one exception where "no template yet" is
           the normal opening state, not a misconfiguration. */
        if (! $template && $code !== 'desk_review') {
            return view('inspection.no-template', compact('type'));
        }

        $entity = $request->filled('entity')
            ? Entity::with('upis')->find($request->integer('entity'))
            : null;

        return view('inspection.form', [
            'type'       => $type,
            'stage'      => $stage,          // <-- passed to the view
            'template'   => $template?->load('sections.items'),

            /* Both checklists, so the form can show whichever the officer
               chooses under Status of the works. */
            'templates'  => collect($templates)->map(fn ($t) => $t->load('sections.items'))->all(),
            'entity'     => $entity,
            'inspection' => null,
            'answers'    => [],
            'previous'   => null,
            'visitType'  => 'new',
            'action'     => route('inspection.store', $code),
        ]);
    }

    public function followUp(string $code, Entity $entity)
    {
        $this->authorizeAbility('inspection.followup');

        $type  = InspectionTypeController::resolve($code);
        $stage = request()->input('stage');

    if (! in_array($code, ['construction', 'desk_review', 'waste_water'], true)) {
            $stage = null;
        }

        

        /* A follow-up offers both stages: the works may have moved from
           substructure to superstructure since the last visit, which is
           often why the officer has returned. */
        $templates = match ($code) {
            'construction' => [
                'Substructure'   => ChecklistTemplate::current($code, 'substructure'),
                'Superstructure' => ChecklistTemplate::current($code, 'superstructure'),
            ],
            'waste_water' => [
                'stp'         => ChecklistTemplate::current($code, 'stp'),
                'septic_tank' => ChecklistTemplate::current($code, 'septic_tank'),
            ],
            
            default => ['' => ChecklistTemplate::current($code, $stage)],
        };

        $templates = array_filter($templates);
        $template  = collect($templates)->first();

        if (! $template) {
            return view('inspection.no-template', compact('type'));
        }

        $previous = $entity->inspections()->where('status', 'completed')->first();

        $answers = $previous
            ? $previous->answers->keyBy('item_id')
                ->map(fn ($a) => ['status' => $a->status, 'comment' => $a->comment])->all()
            : [];

        return view('inspection.form', [
            'type'       => $type,
            'stage'      => $stage,
            'template'   => $template?->load('sections.items'),
            'templates'  => collect($templates)->map(fn ($t) => $t->load('sections.items'))->all(),
            'entity'     => $entity->load('upis'),
            'inspection' => null,
            'answers'    => $answers,
            'previous'   => $previous,
            'visitType'  => 'followup',
            'action'     => route('inspection.store', $code),
        ]);
    }

        public function edit(Inspection $inspection)
    {
        $this->authorizeEdit($inspection);
        $inspection->load(['entity.upis', 'template.sections.items', 'answers', 'photos', 'team']);
        $inspection->load('faults.fault');

        $code = $inspection->type_code;

        $templates = null;
        $template  = $inspection->template;

        /* An inspection with no template attached — every wastewater
           record made before the real checklist existed — is offered
           the same choice a new inspection gets, rather than being
           permanently stuck with no checklist. Choosing a system now
           and answering it gives the record a genuine score going
           forward, the same as if it had been created today. */
        if (! $template && in_array($code, ['construction', 'waste_water'], true)) {
            $templates = match ($code) {
                'construction' => [
                    'Substructure'   => ChecklistTemplate::current($code, 'substructure'),
                    'Superstructure' => ChecklistTemplate::current($code, 'superstructure'),
                ],
                'waste_water' => [
                    'stp'         => ChecklistTemplate::current($code, 'stp'),
                    'septic_tank' => ChecklistTemplate::current($code, 'septic_tank'),
                ],
            };
            $templates = array_filter($templates);
            $template  = collect($templates)->first();
        }

        return view('inspection.form', [
            'type'       => InspectionTypeController::resolve($code),
            'template'   => $template ? $template->load('sections.items') : null,
            'templates'  => $templates
                ? collect($templates)->map(fn ($t) => $t->load('sections.items'))->all()
                : null,
            'entity'     => $inspection->entity,
            'inspection' => $inspection,
            'answers'    => $inspection->answers->keyBy('item_id')
                                ->map(fn ($a) => ['status' => $a->status, 'comment' => $a->comment])->all(),
            'previous'   => $inspection->previous,
            'visitType'  => $inspection->visit_type,
            'action'     => route('inspection.update', $inspection),
        ]);
    }
    
    
        /**
     * Keep the proposed fine in step with the faults.
     *
     * A fault carries a sanction; recording one and then separately
     * proposing a fine asked the officer to do twice what the schedule
     * decided once. Completing the inspection raises the proposal, and
     * editing it adjusts the figure.
     *
     * Only while the fine is still proposed. Once the Secretary has
     * confirmed it against the letter that issued, it is what was served
     * and the platform must not move it — by then the report is sealed
     * and the faults cannot change anyway, but the guard says so rather
     * than relying on that.
     */
    private function syncProposedFine(Inspection $inspection): void
    {
        /* A draft is not a finding. An inspector part-way through a
           checklist has not yet said anything the City would act on. */
        if ($inspection->status !== 'completed') {
            return;
        }

        $inspection->load('faults.fault', 'entity');

        $priced = $inspection->faults->filter(fn ($f) => (float) $f->amount > 0);
        $total  = (float) $priced->sum('amount');

        $fine = $inspection->fines()
            ->whereIn('status', [\App\Models\Fine::PROPOSED])
            ->first();

        /* Nothing to fine. Any proposal raised by an earlier version of
           this inspection is withdrawn — the faults it rested on are no
           longer recorded. */
        if ($total <= 0) {
            $fine?->delete();
            return;
        }

        $reason = $inspection->faults->values()->map(function ($f, $i) {
            $line = ($i + 1) . '. ' . ($f->fault?->title ?? 'Fault');

            if ($f->scope) {
                $line .= ' (' . $f->scope . ')';
            }

            $line .= ' — ' . ((float) $f->amount > 0
                ? 'FRW ' . number_format((float) $f->amount, 0)
                : 'no fine; ' . strtolower(rtrim($f->action ?? 'action as scheduled', '.')));

            return $f->note ? $line . '. ' . $f->note : $line;
        })->implode("\n");

        $payload = [
            'entity_id'      => $inspection->entity_id,
            'district_id'    => $inspection->district_id,
            'status'         => \App\Models\Fine::PROPOSED,
            'amount'         => $total,
            'reason'         => 'Faults found at the inspection of '
                                . ($inspection->entity->name ?? 'the premises')
                                . ' on ' . $inspection->inspection_date->format('j F Y')
                                . ":\n\n" . $reason,
            'legal_basis'    => 'Administrative sanctions schedule, Urban Planning Code',
            'due_date'       => $inspection->inspection_date->copy()->addDays(30),
            'proposed_by'    => auth()->id() ?? $inspection->inspector_id,
            'proposed_at'    => now(),
            'case_reference' => $inspection->case_reference,
        ];

        if ($fine) {
            $fine->update($payload);
            return;
        }

        /* Not raised twice. A fine already confirmed or paid stands, and
           a new proposal beside it would double what the premises owes. */
        if ($inspection->fines()->exists()) {
            return;
        }

        $inspection->fines()->create($payload);
    }

    /* ==============================================================
       Saving
       ============================================================== */

    public function store(Request $request, string $code)
    {
        
        $this->authorizeAbility('inspection.create');
        $data  = $this->validated($request, $code);
        $stage = $data['stage'] ?? null;

        if ($code !== 'construction') {
            $stage = null;
        }

        /* The stage is the status of the works, chosen on the checklist
           rather than before it. Required here even though opening the
           form is not: answers with no checklist cannot be scored. */
        if ($code === 'construction') {
            $stage = strtolower(trim((string) $request->input('building_status')));

            abort_unless(
                in_array($stage, ['substructure', 'superstructure'], true),
                422,
                'Choose whether the works are substructure or superstructure.'
            );
        }
               if ($code === 'desk_review') {
            $stage = $request->input('stage');

            abort_unless(
                in_array($stage, ['ncp','op','renew','mod','rwo','rwi','fence'], true),
                422,
                'Choose the kind of application being reviewed.'
            );
        }
        if ($code === 'waste_water') {
            $stage = strtolower(trim((string) $request->input('wastewater_system_type')));

            abort_unless(
                in_array($stage, ['stp', 'septic_tank'], true),
                422,
                'Choose whether the system is a sewage treatment plant or a septic tank.'
            );
        }

        $template = ChecklistTemplate::current($code, $stage);
        abort_unless($template, 404, 'No published checklist for this category and stage.');

        $this->requireEveryItemAnswered($request, $template);

        return DB::transaction(function () use ($request, $data, $code, $stage, $template) {

            $upis = $this->cleanUpis($request->input('upis', []));

            $entity = Entity::findOrCreateFrom([
                'type_code' => $code,
                'name'      => $data['name'],
                'upi'       => $upis->first(),
                'owner'     => $data['owner'],
                'telephone' => $data['telephone'],
                'email'     => $data['email'],
                'use_type'  => $data['use_type'],
                'zoning'    => $data['zoning'],
                'district'  => $data['district'],
                'sector'    => $data['sector'],
                'cell'      => $data['cell'],
                'latitude'  => $data['latitude']  ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);

            $this->saveUpis($entity, $upis);

            // Clash check (unchanged)
            $clash = Inspection::where('entity_id', $entity->id)
                ->where('type_code', $code)
                ->when($stage, function ($query) use ($stage) {
                    /* A substructure and a superstructure visit on the
                       same day are two inspections, not a duplicate. The
                       same holds for two kinds of desk review. */
                    $query->where('stage', $stage);
                })
                ->whereDate('inspection_date', $data['inspection_date'])
                ->first();

            if ($clash) {
                if ($clash->status === 'draft' && $clash->conductedBy($request->user())) {
                    return redirect()
                        ->route('inspection.edit', $clash)
                        ->with('warning',
                            'You had already begun an inspection of ' . $entity->name
                            . ' on ' . $clash->inspection_date->format('j F Y')
                            . '. It has been opened here so you can finish it rather than start again.');
                }

                return redirect()
                    ->route('inspection.show', $clash)
                    ->with('warning',
                        $entity->name . ' was already inspected on '
                        . $clash->inspection_date->format('j F Y')
                        . ' by ' . $clash->inspector_name
                        . ($clash->status === 'completed'
                            ? ', scoring ' . round((float) $clash->compliance, 1) . '%'
                            : ' and is still in draft')
                        . '. A premises may be inspected once a day for each category. '
                        . 'If a further visit took place, record it under a different date.');
            }

            $inspection = Inspection::create([
                'entity_id'              => $entity->id,
                /* Wastewater has no checklist, so no template to reference —
                   null here is correct, not a missing lookup. */
                'template_id'            => $template?->id,
                'type_code'              => $code,
                'stage'                  => $stage,
                'inspection_date'        => $data['inspection_date'],
                'visit_type'             => $request->input('visit_type') === 'followup' ? 'followup' : 'new',
                'previous_inspection_id' => $request->integer('previous_inspection_id') ?: null,
                'visit_number'           => $entity->inspections()->count() + 1,
                'status'                 => $request->input('action') === 'complete' ? 'completed' : 'draft',
                'inspector_id'           => auth()->id(),
                'inspector_name'         => auth()->user()->name,
                /* Where the premises is, not where the officer is based.
                   Set from the inspector's account, a Kicukiro officer
                   recording a Gasabo site filed it under Kicukiro — and
                   the report, letter and fine all inherited it, routing
                   them to a Director with no authority over the site. */
                'district_id'            => \App\Models\District::where('name', $entity->district)->value('id')
                                            ?? auth()->user()->district_id,
                'observations'           => $data['observations']    ?? null,
                'recommendations'        => $data['recommendations'] ?? null,
                
                'application_number' => $data['application_number'] ?? null,
                'permit_issued_on'   => $data['permit_issued_on']   ?? null,
                'reviewed_on'        => $data['reviewed_on']        ?? null,

                /* The particulars of a construction site. Validated and
                   fillable is not enough: this array names its fields, so
                   anything absent from it is quietly discarded — which is
                   how five inspections were recorded with no permit
                   number and no building category. */
                'permit_number'     => $data['permit_number']     ?? null,
                'permit_expiry'     => $data['permit_expiry']     ?? null,
                'contractor'        => $data['contractor']        ?? null,
                'supervisor'        => $data['supervisor']        ?? null,
                'building_status'   => $data['building_status']   ?? null,
                'dwelling_unit'     => $data['dwelling_unit']     ?? null,
                'has_physical_plan' => $data['has_physical_plan'] ?? null,
                'building_category' => $data['building_category'] ?? null,
                                'on_main_corridor' => $data['on_main_corridor'] ?? null,
                'corridor_road'    => $data['corridor_road']    ?? null,

                'occupation_permit_number'      => $data['occupation_permit_number']      ?? null,
                'occupation_permit_year'        => $data['occupation_permit_year']        ?? null,
                'manager_name'                  => $data['manager_name']                  ?? null,
                'manager_telephone'             => $data['manager_telephone']             ?? null,
                'wastewater_system_type'        => $data['wastewater_system_type']        ?? null,
                'wastewater_system_type_other'  => $data['wastewater_system_type_other']  ?? null,
                'stp_type'                      => $data['stp_type']                      ?? null,
                'stp_type_other'                => $data['stp_type_other']                ?? null,
                'design_capacity_m3'            => $data['design_capacity_m3']            ?? null,
                'population_served'             => $data['population_served']             ?? null,
                'water_consumption_m3'          => $data['water_consumption_m3']          ?? null,
                'year_constructed'              => $data['year_constructed']              ?? null,
                'last_maintenance_date'         => $data['last_maintenance_date']         ?? null,
                'last_desludging_date'          => $data['last_desludging_date']          ?? null,
                'desludging_frequency'          => $data['desludging_frequency']          ?? null,
                'operational_status'            => $data['operational_status']            ?? null,
                'stp_general_condition'         => $data['stp_general_condition']         ?? null,
                'effluent_test_date'            => $data['effluent_test_date']            ?? null,
                'laboratory_name'               => $data['laboratory_name']               ?? null,
                'final_discharge_point'         => $data['final_discharge_point']         ?? null,
                'septic_tank_type'              => $data['septic_tank_type']              ?? null,
                'septic_chamber_count'          => $data['septic_chamber_count']          ?? null,
                'septic_tank_material'          => $data['septic_tank_material']          ?? null,
                'septic_tank_capacity'          => $data['septic_tank_capacity']          ?? null,
                'septic_year_installed'         => $data['septic_year_installed']         ?? null,
                'distance_to_foundation_m'      => $data['distance_to_foundation_m']      ?? null,
                'septic_tank_location'          => $data['septic_tank_location']          ?? null,
                'absorption_field_condition'    => $data['absorption_field_condition']    ?? null,
                'effluent_disposal_method'      => $data['effluent_disposal_method']      ?? null,
                'desludging_provider'           => $data['desludging_provider']           ?? null,
                'tank_adequacy'                 => $data['tank_adequacy']                 ?? null,
                'septic_observations'           => $data['septic_observations']           ?? null,
                'non_compliances'               => $data['non_compliances']               ?? null,
                'non_compliances_other'         => $data['non_compliances_other']         ?? null,
                'key_findings'                  => $data['key_findings']                  ?? null,
                'environmental_risks'           => $data['environmental_risks']           ?? null,
                'wastewater_compliance_status'  => $data['wastewater_compliance_status']  ?? null,
                'decision_taken'                => $data['decision_taken']                ?? null,
                'wastewater_fine_amount'        => $data['wastewater_fine_amount']        ?? null,
                'wastewater_compliance_deadline'=> $data['wastewater_compliance_deadline']?? null,
                'wastewater_followup_date'      => $data['wastewater_followup_date']      ?? null,
            ]);
            $this->saveAnswers($inspection, $request->input('answers', []));
            $this->saveFaults($request, $inspection);
            $this->saveTeam($inspection, $request->input('team', []));
            $this->savePhotos($inspection, $request);
            $inspection->recalculate();
            $this->syncProposedFine($inspection);

            return redirect()->route('inspection.show', $inspection)
                ->with('status', 'Inspection saved for ' . $entity->name . '.');
        });
    }

    public function update(Request $request, Inspection $inspection)
    {
        $this->authorizeEdit($inspection);
        \Log::info('WASTEWATER DEBUG', [
            'has_field'   => $request->has('wastewater_system_type'),
            'raw_input'   => $request->input('wastewater_system_type'),
            'raw_type'    => gettype($request->input('wastewater_system_type')),
            'all_ww_keys' => collect($request->all())->keys()->filter(fn ($k) => str_contains($k, 'wastewater'))->all(),
        ]);

        /* update() takes no $code — the inspection already knows what
           category it belongs to, and that is what decides whether
           zoning is required, same rule as when it was first created. */
        
       $data = $this->validated($request, $inspection->type_code);

        /* An inspection with no template attached — an old wastewater
           record made before the checklist existed, and not yet given
           one via edit() — has nothing to check every item against.
           Skipped here; edit() offers the real choice, and once a
           system type is chosen and the checklist answered, template_id
           is set and this check applies normally from then on. */
                if ($inspection->template) {
            $this->requireEveryItemAnswered($request, $inspection->template);
        }

        /* An old wastewater record with no template yet gets one now,
           from whichever system type was chosen on this edit — the same
           choice a new inspection makes, just happening for the first
           time here instead of at creation. Once set, this never runs
           again for this record: template_id is written once, the same
           way every other category's is decided only at creation and
           never revisited by an edit. */
        $newTemplateId = null;

        if ($inspection->type_code === 'waste_water' && ! $inspection->template_id) {
            $stage = strtolower(trim((string) $request->input('wastewater_system_type')));

            abort_unless(
                in_array($stage, ['stp', 'septic_tank'], true),
                422,
                'Choose whether the system is a sewage treatment plant or a septic tank.'
            );

            $newTemplateId = ChecklistTemplate::current('waste_water', $stage)?->id;
        }

        return DB::transaction(function () use ($request, $data, $inspection, $newTemplateId) {

            $upis   = $this->cleanUpis($request->input('upis', []));
            $entity = $inspection->entity;

            $entity->fill([
                'name'      => $data['name'],
                'upi'       => $upis->first(),
                'owner'     => $data['owner'],
                'telephone' => $data['telephone'],
                'email'     => $data['email'],
                'use_type'  => $data['use_type'],
                'zoning'    => $data['zoning'],
                'district'  => $data['district'],
                'sector'    => $data['sector'],
                'cell'      => $data['cell'],
                'latitude'  => $data['latitude']  ?? $entity->latitude,
                'longitude' => $data['longitude'] ?? $entity->longitude,
            ])->save();

            $this->saveUpis($entity, $upis);

                        $inspection->fill([
                'inspection_date' => $data['inspection_date'],
                'observations'    => $data['observations']    ?? null,
                'recommendations' => $data['recommendations'] ?? null,
                'status'          => $request->input('action') === 'complete' ? 'completed' : $inspection->status,

                /* Named here as well as on creation, or editing would
                   clear what was entered when the record was made. */
                'permit_number'      => $data['permit_number']      ?? $inspection->permit_number,
                'permit_expiry'      => $data['permit_expiry']      ?? $inspection->permit_expiry,
                'contractor'         => $data['contractor']         ?? $inspection->contractor,
                'supervisor'         => $data['supervisor']         ?? $inspection->supervisor,
                'building_status'    => $data['building_status']    ?? $inspection->building_status,
                'dwelling_unit'      => $data['dwelling_unit']      ?? $inspection->dwelling_unit,
                'has_physical_plan'  => $data['has_physical_plan']  ?? $inspection->has_physical_plan,
                'building_category'  => $data['building_category']  ?? $inspection->building_category,
                'on_main_corridor'   => $data['on_main_corridor']   ?? $inspection->on_main_corridor,
                'corridor_road'      => $data['corridor_road']      ?? $inspection->corridor_road,
                'application_number' => $data['application_number'] ?? $inspection->application_number,
                'permit_issued_on'   => $data['permit_issued_on']   ?? $inspection->permit_issued_on,
                'reviewed_on'        => $data['reviewed_on']        ?? $inspection->reviewed_on,
                
                
                'template_id' => $newTemplateId ?? $inspection->template_id,

                'wastewater_system_type' => tap($data['wastewater_system_type'] ?? $inspection->wastewater_system_type, function ($v) use ($data) {
                    \Log::info('WASTEWATER DEBUG 2', [
                        'data_has_key' => array_key_exists('wastewater_system_type', $data),
                        'data_value'   => $data['wastewater_system_type'] ?? 'MISSING FROM DATA',
                        'about_to_write' => $v,
                    ]);
                }),

                'occupation_permit_number'      => $data['occupation_permit_number']      ?? $inspection->occupation_permit_number,
                

                /* Written only the first time an old wastewater record
                   is given a checklist — every subsequent edit leaves
                   it exactly as it already is. */
                'template_id' => $newTemplateId ?? $inspection->template_id,

                'occupation_permit_number'      => $data['occupation_permit_number']      ?? $inspection->occupation_permit_number,
                'occupation_permit_year'        => $data['occupation_permit_year']        ?? $inspection->occupation_permit_year,
                'manager_name'                  => $data['manager_name']                  ?? $inspection->manager_name,
                'manager_telephone'             => $data['manager_telephone']             ?? $inspection->manager_telephone,
                'wastewater_system_type'        => $data['wastewater_system_type']        ?? $inspection->wastewater_system_type,
                'wastewater_system_type_other'  => $data['wastewater_system_type_other']  ?? $inspection->wastewater_system_type_other,
                'stp_type'                      => $data['stp_type']                      ?? $inspection->stp_type,
                'stp_type_other'                => $data['stp_type_other']                ?? $inspection->stp_type_other,
                'design_capacity_m3'            => $data['design_capacity_m3']            ?? $inspection->design_capacity_m3,
                'population_served'             => $data['population_served']             ?? $inspection->population_served,
                'water_consumption_m3'          => $data['water_consumption_m3']          ?? $inspection->water_consumption_m3,
                'year_constructed'              => $data['year_constructed']              ?? $inspection->year_constructed,
                'last_maintenance_date'         => $data['last_maintenance_date']         ?? $inspection->last_maintenance_date,
                'last_desludging_date'          => $data['last_desludging_date']          ?? $inspection->last_desludging_date,
                'desludging_frequency'          => $data['desludging_frequency']          ?? $inspection->desludging_frequency,
                'operational_status'            => $data['operational_status']            ?? $inspection->operational_status,
                'stp_general_condition'         => $data['stp_general_condition']         ?? $inspection->stp_general_condition,
                'effluent_test_date'            => $data['effluent_test_date']            ?? $inspection->effluent_test_date,
                'laboratory_name'               => $data['laboratory_name']               ?? $inspection->laboratory_name,
                'final_discharge_point'         => $data['final_discharge_point']         ?? $inspection->final_discharge_point,
                'septic_tank_type'              => $data['septic_tank_type']              ?? $inspection->septic_tank_type,
                'septic_chamber_count'          => $data['septic_chamber_count']          ?? $inspection->septic_chamber_count,
                'septic_tank_material'          => $data['septic_tank_material']          ?? $inspection->septic_tank_material,
                'septic_tank_capacity'          => $data['septic_tank_capacity']          ?? $inspection->septic_tank_capacity,
                'septic_year_installed'         => $data['septic_year_installed']         ?? $inspection->septic_year_installed,
                'distance_to_foundation_m'      => $data['distance_to_foundation_m']      ?? $inspection->distance_to_foundation_m,
                'septic_tank_location'          => $data['septic_tank_location']          ?? $inspection->septic_tank_location,
                'absorption_field_condition'    => $data['absorption_field_condition']    ?? $inspection->absorption_field_condition,
                'effluent_disposal_method'      => $data['effluent_disposal_method']      ?? $inspection->effluent_disposal_method,
                'desludging_provider'           => $data['desludging_provider']           ?? $inspection->desludging_provider,
                'tank_adequacy'                 => $data['tank_adequacy']                 ?? $inspection->tank_adequacy,
                'septic_observations'           => $data['septic_observations']           ?? $inspection->septic_observations,
                'non_compliances'               => $data['non_compliances']               ?? $inspection->non_compliances,
                'non_compliances_other'         => $data['non_compliances_other']         ?? $inspection->non_compliances_other,
                'key_findings'                  => $data['key_findings']                  ?? $inspection->key_findings,
                'environmental_risks'           => $data['environmental_risks']           ?? $inspection->environmental_risks,
                'wastewater_compliance_status'  => $data['wastewater_compliance_status']  ?? $inspection->wastewater_compliance_status,
                'decision_taken'                => $data['decision_taken']                ?? $inspection->decision_taken,
                'wastewater_fine_amount'        => $data['wastewater_fine_amount']        ?? $inspection->wastewater_fine_amount,
                'wastewater_compliance_deadline'=> $data['wastewater_compliance_deadline']?? $inspection->wastewater_compliance_deadline,
                'wastewater_followup_date'      => $data['wastewater_followup_date']      ?? $inspection->wastewater_followup_date,
            ])->save();

            $this->saveAnswers($inspection, $request->input('answers', []));
            $this->saveTeam($inspection, $request->input('team', []));
            $this->savePhotos($inspection, $request);
            $inspection->recalculate();
            $this->syncProposedFine($inspection);
            return redirect()->route('inspection.show', $inspection)
                ->with('status', 'Inspection updated.');
        });
    }

    /* ==============================================================
       Viewing and export
       ============================================================== */

    public function show(Inspection $inspection)
    {
        $inspection->load([
            'entity.upis', 'template.sections.items', 'answers', 'team', 'inspector', 'photos',
        ]);

        $history = $inspection->entity->inspections;
        $prev    = $history->firstWhere('visit_number', $inspection->visit_number - 1);

        return view('inspection.show', [
            'inspection'      => $inspection,
            'answers'         => $inspection->answers->keyBy('item_id'),
            'canEdit'         => $this->canEdit($inspection),
            'history'         => $history,
            'previousCompare' => $prev
                ? (float) $inspection->compliance - (float) $prev->compliance
                : null,
        ]);
    }

    /**
     * Download the report as a Word document.
     *
     * Produced as Word-flavoured HTML rather than through a library, so
     * it needs no additional dependency on a host where proc_open is
     * disabled. Word opens it, renders it, and allows it to be edited
     * and re-saved as .docx.
     */
    public function word(Inspection $inspection)
    {
        $inspection->load(['entity.upis', 'template.sections.items', 'answers', 'team', 'photos']);

        $filename = 'Inspection-Report-'
            . preg_replace('/[^A-Za-z0-9]+/', '-', $inspection->entity->name) . '-'
            . $inspection->inspection_date->format('Y-m-d') . '.doc';

        /* The Director who covers the premises, not the one who covers
           the inspector. district_id on the inspection is the officer's
           own — so a Kicukiro inspector recording a Nyarugenge site had
           Kicukiro's Director named above the words "Nyarugenge District".
           A report signed on that basis would be wrong on its face.
        $director = \App\Models\User::whereHas('roles', fn ($q) =>
                $q->where('name', 'Director of Inspection'))
            ->where('is_active', true)
            ->get()
            ->first(fn ($u) => $u->coversDistrict($inspection->entity->district));*/
        
        $director = \App\Models\User::whereHas('roles', fn ($q) =>
                $q->where('name', 'Director of Inspection'))
            ->where('is_active', true)
            ->get()
            ->first(fn ($u) => $u->coversDistrict($inspection->district_id));
        $inspection->load('faults.fault');

        $html = view('inspection.report-word', [
            'inspection' => $inspection,
            'answers'    => $inspection->answers->keyBy('item_id'),
            'history'    => $inspection->entity->inspections,
            'director'   => $director,
        ])->render();

        return response($html, 200, [
            'Content-Type'        => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
    
        /**
     * Faults found at the inspection.
     *
     * The amount and the action are copied from the schedule as it stands
     * today. The schedule may be amended; what was found and what was set
     * against it on the day must not change with it.
     *
     * Nothing here raises a fine. These are findings, like every other
     * finding on this platform: the consequence follows the sealed report
     * and the letter.
     */
    private function saveFaults(Request $request, Inspection $inspection): void
    {
        if ($inspection->type_code !== 'construction') {
            return;
        }

        $submitted = collect($request->input('faults', []))
            ->filter(fn ($f) => ! empty($f['selected']));

        $inspection->faults()->whereNotIn('fault_id', $submitted->keys())->delete();

        foreach ($submitted as $faultId => $f) {
            $fault = \App\Models\Fault::with('sanctions')->find($faultId);
            if (! $fault) continue;

            /* Where the sanction turns on a road class or a protected
               area the officer chooses it; otherwise it follows the
               building category recorded above. */
            $scope = $f['scope'] ?? $inspection->building_category;

            $sanction = $fault->sanctionFor($scope);

            $inspection->faults()->updateOrCreate(
                ['fault_id' => $fault->id],
                [
                    'fault_sanction_id' => $sanction?->id,
                    'scope'             => $sanction?->scope ?? $scope,
                    'amount'            => $sanction?->amount,
                    'action'            => $sanction?->action,
                    'note'              => $f['note'] ?? null,
                    'recorded_by'       => auth()->id(),
                ]
            );
        }
    }

    /* ==============================================================
       Validation
       ============================================================== */
    private function validated(Request $request, string $code): array
    {
        return $request->validate([
            'name'            => ['required', 'string', 'max:200'],
            'inspection_date' => ['required', 'date', 'before_or_equal:today'],
            /* Construction has two stages, desk review seven kinds.
               Both live in the same column. */
            'stage' => ['nullable', 'in:substructure,superstructure,ncp,op,renew,mod,rwo,rwi,fence'],
            'owner'           => ['required', 'string', 'max:160'],
            'telephone'       => ['required', 'string', 'max:40'],
            'email'           => ['required', 'email', 'max:180'],
            /* Every other category zones a premises as part of what is
               being judged. A wastewater inspection is not a zoning
               judgement, and the paper form this follows does not ask
               for it — required everywhere else, not here. */
            'zoning'          => ['required', 'string', 'max:20'],
            'use_type'        => ['required', 'string', 'max:120'],
            'district'        => ['required', 'string', 'max:60'],
            'sector'          => ['required', 'string', 'max:60'],
            'cell'            => ['required', 'string', 'max:60'],

            'latitude'  => ['nullable', 'numeric', 'between:-2.6,-1.4'],
            'longitude' => ['nullable', 'numeric', 'between:29.4,30.7'],

            'upis'            => ['required', 'array', 'min:1', 'max:6'],
            'upis.*'          => ['nullable', 'regex:/^[1-5]\/\d{2}\/\d{2}\/\d{2}\/\d+(-\d+)?$/'],

            'observations'    => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
            'permit_number'     => ['nullable', 'string', 'max:60'],
            'permit_expiry'     => ['nullable', 'date'],
            'contractor'        => ['nullable', 'string', 'max:255'],
            'supervisor'        => ['nullable', 'string', 'max:255'],
            'building_status'   => ['nullable', 'string', 'max:80'],
            'dwelling_unit'     => ['nullable', 'string', 'max:80'],
            'has_physical_plan' => ['nullable', 'boolean'],
            'building_category' => ['nullable', 'string', 'max:80'],
                        'faults'                => ['nullable', 'array'],
            'faults.*.selected'     => ['nullable', 'in:1'],
            'faults.*.scope'        => ['nullable', 'string', 'max:80'],
            'faults.*.note'         => ['nullable', 'string', 'max:300'],
            
            'on_main_corridor' => ['nullable', 'boolean'],
            'corridor_road'    => ['nullable', 'string', 'max:120'],
            
            'application_number' => ['nullable', 'string', 'max:60'],
            'permit_issued_on'   => ['nullable', 'date', 'before_or_equal:today'],
            'reviewed_on'        => ['nullable', 'date', 'before_or_equal:today'],
            
                        'occupation_permit_number' => ['nullable', 'string', 'max:60'],
            'occupation_permit_year'   => ['nullable', 'integer', 'min:1950', 'max:' . date('Y')],
            'manager_name'             => ['nullable', 'string', 'max:160'],
            'manager_telephone'        => ['nullable', 'string', 'max:40'],
            'wastewater_system_type'       => ['nullable', 'string', 'in:stp,septic_tank,public_sewer,soak_pit,none,other'],
            'wastewater_system_type_other' => ['nullable', 'string', 'max:120'],
            'stp_type'               => ['nullable', 'string', 'max:40'],
            'stp_type_other'         => ['nullable', 'string', 'max:120'],
            'design_capacity_m3'     => ['nullable', 'numeric', 'min:0'],
            'population_served'      => ['nullable', 'integer', 'min:0'],
            'water_consumption_m3'   => ['nullable', 'numeric', 'min:0'],
            'year_constructed'       => ['nullable', 'integer', 'min:1950', 'max:' . date('Y')],
            'last_maintenance_date'  => ['nullable', 'date', 'before_or_equal:today'],
            'last_desludging_date'   => ['nullable', 'date', 'before_or_equal:today'],
            'desludging_frequency'   => ['nullable', 'string', 'max:30'],
            'operational_status'     => ['nullable', 'string', 'max:30'],
            'stp_general_condition'  => ['nullable', 'string'],
            'effluent_test_date'     => ['nullable', 'date', 'before_or_equal:today'],
            'laboratory_name'        => ['nullable', 'string', 'max:255'],
            'final_discharge_point'  => ['nullable', 'string', 'max:40'],
            'septic_tank_type'           => ['nullable', 'string', 'max:30'],
            'septic_chamber_count'       => ['nullable', 'integer', 'min:1', 'max:10'],
            'septic_tank_material'       => ['nullable', 'string', 'max:30'],
            'septic_tank_capacity'       => ['nullable', 'numeric', 'min:0'],
            'septic_year_installed'      => ['nullable', 'integer', 'min:1950', 'max:' . date('Y')],
            'distance_to_foundation_m'   => ['nullable', 'numeric', 'min:0'],
            'septic_tank_location'       => ['nullable', 'string', 'max:30'],
            'absorption_field_condition' => ['nullable', 'string', 'max:20'],
            'effluent_disposal_method'   => ['nullable', 'string', 'max:30'],
            'desludging_provider'        => ['nullable', 'string', 'max:255'],
            'tank_adequacy'              => ['nullable', 'string', 'max:20'],
            'septic_observations'        => ['nullable', 'string'],
            'non_compliances'            => ['nullable', 'array'],
            'non_compliances.*'          => ['nullable', 'string'],
            'non_compliances_other'      => ['nullable', 'string', 'max:255'],
            'key_findings'               => ['nullable', 'string'],
            'environmental_risks'        => ['nullable', 'string'],
            'wastewater_compliance_status'   => ['nullable', 'string', 'in:compliant,non_compliant'],
            'decision_taken'                 => ['nullable', 'string', 'in:advisory_notice,improvement_notice,warning,fine_issued,temporary_closure,permanent_closure,reinspection_required'],
            'wastewater_fine_amount'         => ['nullable', 'integer', 'min:0'],
            'wastewater_compliance_deadline' => ['nullable', 'date'],
            'wastewater_followup_date'       => ['nullable', 'date'],

            'team'            => ['nullable', 'array', 'max:8'],
            'team.*.name'     => ['nullable', 'string', 'max:120'],
            'team.*.position' => ['nullable', 'string', 'max:120'],
            'team.*.institution' => ['nullable', 'string', 'max:160'],

            'photos'          => ['nullable', 'array', 'max:' . self::MAX_PHOTOS],
            'photos.*'        => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ], [
            'upis.*.regex'   => 'Each parcel identifier must look like 1/02/05/03/4745.',
            'email.required' => 'An email address is required — enforcement letters are sent to it.',
            'photos.max'     => 'A maximum of three photographs may be attached.',
            
        ]);
    }

    
    /**
     * Every checklist item must be answered before completion.
     *
     * The item IDs are flashed back to the session rather than listed in
     * a message, so the form can mark each one in place and take the
     * inspector straight to it. A list of item names in an error box is
     * of little use when the checklist runs to ninety-one rows.
     */
    private function requireEveryItemAnswered(Request $request, ChecklistTemplate $template): void
    {
        if ($request->input('action') !== 'complete') {
            return;
        }

        $answers = $request->input('answers', []);
        $missing = [];

        foreach ($template->load('sections.items')->sections as $section) {
            foreach ($section->items as $item) {
                $status = $answers[$item->id]['status'] ?? null;
                if (! in_array($status, ['yes', 'no', 'na'], true)) {
                    $missing[] = $item->id;
                }
            }
        }

        if (empty($missing)) {
            return;
        }

        session()->flash('missing_items', $missing);

        throw ValidationException::withMessages([
            'checklist' => count($missing) === 1
                ? 'One checklist item still needs an answer. It is marked below.'
                : count($missing) . ' checklist items still need an answer. They are marked below.',
        ]);
    }

    /* ==============================================================
       Persistence helpers
       ============================================================== */

    private function cleanUpis(array $upis)
    {
        return collect($upis)->map(fn ($u) => trim((string) $u))->filter()->unique()->values();
    }

    private function saveUpis(Entity $entity, $upis): void
    {
        if ($upis->isEmpty()) {
            return;
        }

        $entity->upis()->whereNotIn('upi', $upis->all())->delete();

        foreach ($upis as $i => $upi) {
            EntityUpi::updateOrCreate(
                ['entity_id' => $entity->id, 'upi' => $upi],
                ['is_primary' => $i === 0]
            );
        }
    }

    private function saveAnswers(Inspection $inspection, array $answers): void
    {
        foreach ($answers as $itemId => $answer) {
            $status = $answer['status'] ?? null;
            if (! in_array($status, ['yes', 'no', 'na'], true)) {
                $status = null;
            }

            InspectionAnswer::updateOrCreate(
                ['inspection_id' => $inspection->id, 'item_id' => (int) $itemId],
                ['status' => $status, 'comment' => $answer['comment'] ?? null]
            );
        }
    }

    /**
     * The officers who conducted the site visit.
     *
     * Their names and positions form the signature block of the report,
     * so the document is signed by the people who were actually present
     * rather than by a generic title.
     */
    private function saveTeam(Inspection $inspection, array $team): void
    {
        $inspection->team()->delete();

        foreach ($team as $i => $member) {
            $name = trim((string) ($member['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            \App\Models\InspectionTeamMember::create([
                'inspection_id' => $inspection->id,
                'name'          => $name,
                'position'      => $member['position'] ?? null,
                'institution'   => $member['institution'] ?? 'City of Kigali',
                'is_lead'       => $i === 0,
            ]);
        }
    }

    private function savePhotos(Inspection $inspection, Request $request): void
    {
        foreach ((array) $request->input('remove_photos', []) as $id) {
            $inspection->photos()->where('id', $id)->get()->each->delete();
        }

        $count = $inspection->photos()->count();

        foreach ((array) $request->file('photos', []) as $file) {
            if (! $file || $count >= self::MAX_PHOTOS) {
                break;
            }

            $path = $file->store('inspections/' . $inspection->id, 'public');

            InspectionPhoto::create([
                'inspection_id' => $inspection->id,
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'sort_order'    => $count,
                'size_bytes'    => $file->getSize(),
            ]);

            $count++;
        }
    }

    /* ==============================================================
       Authorisation
       ============================================================== */

        private function canEdit(Inspection $inspection): bool
    {
        $user = auth()->user();

        /* A sealed report has been attested to by the Director. Altering
           the inspection beneath it would leave the document describing
           findings that are no longer there — and any fine raised from
           those faults resting on something the seal does not cover.
           Faults stay editable until the report is signed, and not after.
           This applies to everyone, including the officer who conducted
           it — a seal is a seal regardless of who is asking. */
        if ($inspection->hasSealedReport()) {
            return false;
        }

        /* The officer who conducted the inspection may always edit their
           own submission. A district reassignment afterward should not
           lock someone out of work they personally carried out — the
           district check below is only for editing someone else's
           inspection, not your own. */
        if ($user->can('inspection.edit.own') && $inspection->inspector_id === $user->id) {
            return true;
        }

        if (! $user->coversDistrict($inspection->district_id)) {
            return false;
        }

        return $user->can('inspection.edit.district');
    }

    private function authorizeEdit(Inspection $inspection): void
    {
        abort_unless($this->canEdit($inspection), 403,
            'You may only edit inspections you carried out, or those in your district.');
    }

    private function authorizeAbility(string $permission): void
    {
        abort_unless(auth()->user()->can($permission), 403);
    }

    /**
     * Remove an inspection that should never have been recorded.
     *
     * This is for the wrong premises, or a visit entered twice - not for
     * an inspection somebody has come to regret. Only an inspector who was
     * there may do it, and only while nothing has been put on the record:
     * once a report carries a signature or a letter has been issued, the
     * visit is part of an enforcement trail and stays.
     *
     * Nothing is destroyed. The inspection, its letters and its fines are
     * all soft deleted, and who removed it and why is kept on the row.
     */
    public function destroy(Request $request, Inspection $inspection)
    {
        // The team is the authority here, not the district or the rank.
        // conductedBy() covers the lead inspector and every team member.
        abort_unless($inspection->conductedBy($request->user()), 403,
            'Only an inspector who carried out this visit may delete it.');

        $data = $request->validate([
            'reason'  => ['required', 'string', 'max:300'],
            'confirm' => ['required', 'in:DELETE'],
        ], [
            'confirm.in' => 'Type DELETE to confirm that the inspection should be removed.',
        ]);

        $report = $inspection->report();

        // A signature is the point of no return. Sealed or not, once
        // somebody has put their name to the report the visit is evidence.
        if ($report && ($report->sealed_at || $report->signatures()->exists())) {
            return back()->withErrors([
                'reason' => 'The report for this inspection has been signed, so it can no longer be deleted.',
            ]);
        }

        if ($inspection->documents()->where('status', Document::ISSUED)->exists()) {
            return back()->withErrors([
                'reason' => 'A letter has been issued for this inspection, so it can no longer be deleted.',
            ]);
        }

        DB::transaction(function () use ($inspection, $data, $report) {
            // Recorded against the report where there is one, so the
            // deletion leaves a trail that outlives the rows it describes.
            if ($report) {
                DocumentTransition::create([
                    'document_id' => $report->id,
                    'from_status' => $report->status,
                    'to_status'   => Document::CANCELLED,
                    'actor_id'    => auth()->id(),
                    'actor_role'  => auth()->user()->roles->pluck('name')->first() ?? 'Inspector',
                    'comment'     => 'Inspection deleted — ' . $data['reason'],
                    'ip_address'  => request()->ip(),
                ]);
            }

            // Both soft delete, so a letter drafted against this visit and
            // any fine proposed on it can be recovered with it. Anything
            // signed or issued was refused above, so only drafts get here.
            $inspection->documents()->get()->each->delete();
            $inspection->fines()->get()->each->delete();

            $inspection->forceFill([
                'deleted_by'     => auth()->id(),
                'deleted_reason' => $data['reason'],
            ])->save();

            $inspection->delete();
        });

        return redirect()->route('inspection.drafts')
            ->with('status', 'The inspection has been deleted and the reason recorded.');
    }
}