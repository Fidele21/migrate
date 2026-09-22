import re, sys

p = "app/Http/Controllers/InspectionController.php"
s = open(p, encoding="utf-8").read()
done = []

# ── 1. create(): allow a stage for desk review, and serve nothing until chosen
old = """        $type  = InspectionTypeController::resolve($code);
        $stage = $request->input('stage');

        if ($code !== 'construction') {
            $stage = null;
        }

        /* The stage is chosen on the checklist, under Status of the
           works. Both checklists go to the page and the form shows the
           one that applies. */

        /* Both checklists, because the officer chooses the stage on the
           form. Rendering one and reloading on change would discard
           whatever had already been answered. */
        $templates = $code === 'construction'
            ? [
                'Substructure'   => ChecklistTemplate::current($code, 'substructure'),
                'Superstructure' => ChecklistTemplate::current($code, 'superstructure'),
              ]
            : ['' => ChecklistTemplate::current($code, $stage)];

        $templates = array_filter($templates);

        /* Whether the category is configured at all. Asking for one with
           no stage finds nothing for construction, since both of its
           checklists carry one. */
        $template = collect($templates)->first();

        if (! $template) {
            return view('inspection.no-template', compact('type'));
        }
"""

new = """        $type  = InspectionTypeController::resolve($code);
        $stage = $request->input('stage');

        /* Only two categories are inspected in more than one way. */
        if (! in_array($code, ['construction', 'desk_review'], true)) {
            $stage = null;
        }

        /* Desk review has seven checklists — 122 items between them, too
           many to hold on one page and toggle as construction does with
           two. The kind of application is chosen on the form and the page
           reloads with its checklist. Until then there is no checklist,
           which is the normal opening state rather than a
           misconfiguration. */
        if ($code === 'desk_review') {
            $template = $stage ? ChecklistTemplate::current($code, $stage) : null;

            /* Nothing published at all for any kind — that is a
               misconfiguration, and worth saying so. */
            if (! $stage && ! ChecklistTemplate::where('type_code', $code)
                    ->whereNotNull('published_at')->exists()) {
                return view('inspection.no-template', compact('type'));
            }

            return view('inspection.form', [
                'type'       => $type,
                'stage'      => $stage,
                'template'   => $template?->load('sections.items'),
                'templates'  => array_filter(['' => $template]),
                'entity'     => $request->filled('entity')
                                    ? Entity::with('upis')->find($request->integer('entity'))
                                    : null,
                'inspection' => null,
                'answers'    => [],
                'previous'   => null,
                'visitType'  => 'new',
                'action'     => route('inspection.store', $code),
            ]);
        }

        /* Construction carries both, because the officer chooses the
           stage on the form and reloading would discard whatever had
           already been answered. */
        $templates = $code === 'construction'
            ? [
                'Substructure'   => ChecklistTemplate::current($code, 'substructure'),
                'Superstructure' => ChecklistTemplate::current($code, 'superstructure'),
              ]
            : ['' => ChecklistTemplate::current($code, $stage)];

        $templates = array_filter($templates);
        $template  = collect($templates)->first();

        if (! $template) {
            return view('inspection.no-template', compact('type'));
        }
"""

if old in s:
    s = s.replace(old, new, 1); done.append("create() handles desk review")

# ── 2. followUp(): build $templates, and cut the unreachable desk block
a = s.find("    public function followUp(string $code, Entity $entity)")
b = s.find("    public function edit(Inspection $inspection)")

if a != -1 and b != -1:
    new_follow = """    public function followUp(string $code, Entity $entity)
    {
        $this->authorizeAbility('inspection.followup');

        $type  = InspectionTypeController::resolve($code);
        $stage = request()->input('stage');

        if (! in_array($code, ['construction', 'desk_review'], true)) {
            $stage = null;
        }

        /* A follow-up offers both stages: the works may have moved from
           substructure to superstructure since the last visit, which is
           often why the officer has returned. */
        $templates = $code === 'construction'
            ? [
                'Substructure'   => ChecklistTemplate::current($code, 'substructure'),
                'Superstructure' => ChecklistTemplate::current($code, 'superstructure'),
              ]
            : ['' => ChecklistTemplate::current($code, $stage)];

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
            'template'   => $template->load('sections.items'),
            'templates'  => collect($templates)->map(fn ($t) => $t->load('sections.items'))->all(),
            'entity'     => $entity->load('upis'),
            'inspection' => null,
            'answers'    => $answers,
            'previous'   => $previous,
            'visitType'  => 'followup',
            'action'     => route('inspection.store', $code),
        ]);
    }

"""
    s = s[:a] + new_follow + s[b:]
    done.append("followUp() rebuilt, dead desk-review block removed")

# ── 3. validation must allow the seven desk review kinds
old3 = "            'stage'           => ['nullable', 'in:substructure,superstructure'],"
new3 = """            /* Construction has two stages, desk review seven kinds.
               Both live in the same column. */
            'stage' => ['nullable', 'in:substructure,superstructure,ncp,op,renew,mod,rwo,rwi,fence'],"""
if old3 in s:
    s = s.replace(old3, new3, 1); done.append("stage validation widened")

# ── 4. update(): the corridor fields belong to the inspection, not the entity
old4 = """                'longitude' => $data['longitude'] ?? $entity->longitude,
                'on_main_corridor' => $data['on_main_corridor'] ?? $inspection->on_main_corridor,
                'corridor_road'    => $data['corridor_road']    ?? $inspection->corridor_road,
            ])->save();"""
new4 = """                'longitude' => $data['longitude'] ?? $entity->longitude,
            ])->save();"""
if old4 in s:
    s = s.replace(old4, new4, 1); done.append("corridor fields taken off the entity")

old5 = """                'status'          => $request->input('action') === 'complete' ? 'completed' : $inspection->status,
            ])->save();"""
new5 = """                'status'          => $request->input('action') === 'complete' ? 'completed' : $inspection->status,

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
            ])->save();"""
if old5 in s and "'application_number' => $data['application_number'] ?? $inspection" not in s:
    s = s.replace(old5, new5, 1); done.append("update() saves all particulars")

# ── 5. the clash check must distinguish desk review kinds too
old6 = """                ->when($code === 'construction', function ($query) use ($stage) {
                    $query->where('stage', $stage);
                })"""
new6 = """                ->when($stage, function ($query) use ($stage) {
                    /* A substructure and a superstructure visit on the
                       same day are two inspections, not a duplicate. The
                       same holds for two kinds of desk review. */
                    $query->where('stage', $stage);
                })"""
if old6 in s:
    s = s.replace(old6, new6, 1); done.append("clash check honours any stage")

open(p, "w", encoding="utf-8").write(s)
print("\n".join(done) if done else "NOTHING CHANGED")