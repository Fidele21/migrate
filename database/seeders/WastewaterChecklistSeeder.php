<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Models\ChecklistTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Wastewater management — two checklists, one for each system found on
 * site. A sewage treatment plant and a septic tank are different pieces
 * of infrastructure with different failure modes; the inspector records
 * which one is present on the general form, and the platform serves the
 * matching checklist, the same as construction's substructure and
 * superstructure.
 *
 * Every item here carries the weight the source spreadsheet gives it —
 * 1 mark each, 17 points for the STP checklist and 14 for the Septic
 * Tank checklist, matching the totals the spreadsheet itself states.
 *
 * A handful of items in the source spreadsheet are not yes/no questions
 * at all — Desludging Frequency, Final Discharge Point, Condition of
 * Absorption Field, Effluent Disposal Method, and Tank Adequacy are each
 * a dropdown of several choices, yet each still carries 1 mark. The
 * platform's scoring only understands yes/no/na, so each becomes a
 * yes/no question asking whether the observation was recorded at all —
 * the mark rewards the field being properly documented, not which
 * specific option was true. The actual value chosen is captured
 * separately, as its own field on the inspection, the same way
 * construction keeps its permit number and building category outside
 * the scored checklist.
 */
class WastewaterChecklistSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->publish('stp', 'Wastewater Management — Sewage Treatment Plant', $this->stp());
            $this->publish('septic_tank', 'Wastewater Management — Septic Tank', $this->septicTank());
        });
    }

    private function publish(string $stage, string $name, array $sections): void
    {
        $next = (int) ChecklistTemplate::where('type_code', 'waste_water')
            ->where('stage', $stage)->max('version') + 1;

        $template = ChecklistTemplate::create([
            'type_code'    => 'waste_water',
            'stage'        => $stage,
            'name'         => $name,
            'version'      => $next,
            'published_at' => now(),
        ]);

        foreach ($sections as $order => $sec) {
            $section = ChecklistSection::create([
                'template_id'    => $template->id,
                'section_number' => $sec[0],
                'title'          => $sec[1],
                'sort_order'     => $order + 1,
            ]);

            foreach ($sec[2] as $i => $item) {
                ChecklistItem::create([
                    'section_id'     => $section->id,
                    'item_code'      => $sec[0] . '.' . ($i + 1),
                    'label'          => $item[0],
                    'max_score'      => $item[1],
                    'allows_na'      => true,
                    'is_prohibition' => false,
                    'sort_order'     => $i + 1,
                ]);
            }
        }

        $points = collect($sections)->flatMap(fn ($s) => $s[2])->sum(fn ($i) => $i[1]);
        $items  = collect($sections)->sum(fn ($s) => count($s[2]));

        $this->command->info(ucfirst(str_replace('_', ' ', $stage)) . " v{$next} — "
            . count($sections) . " sections, {$items} items, {$points} points.");
    }

    /**
     * The STP checklist. 17 points, matching the spreadsheet's own
     * stated total exactly.
     */
    private function stp(): array
    {
        return [
            ['1', 'Maintenance Records', [
                ['Maintenance company name recorded?', 1],
                ['Does the maintenance company have a license?', 1],
                ['Maintenance log book available?', 1],
                ['Desludging frequency recorded?', 1],
            ]],
            ['2', 'Operational Status', [
                ['Screening Chamber', 1],
                ['Pumps', 1],
                ['Air Blowers', 1],
                ['Chlorination System', 1],
                ['Oil & Grease Trap', 1],
                ['Sludge Drying Bed', 1],
                ['Ventilation', 1],
            ]],
            ['3', 'Environmental Compliance', [
                ['Effluent Test Results Available', 1],
                ['Effluent Test Results Valid', 1],
                ['Effluent Meets Standards', 1],
                ['No Evidence of Untreated Discharge', 1],
                ['No Evidence of Pollution (odour, overflow, turbidity, foam, solid waste, oil & grease, dead vegetation, wetland/river pollution)', 1],
                ['Final discharge point recorded?', 1],
            ]],
        ];
    }

    /**
     * The Septic Tank checklist. 14 points, matching the spreadsheet's
     * own stated total exactly.
     */
    private function septicTank(): array
    {
        return [
            ['1', 'Tank Components', [
                ['Inlet Pipe', 1],
                ['Outlet Pipe / Baffle Wall', 1],
                ['Access Cover / Manhole', 1],
                ['Vent Pipe', 1],
                ['Scum Layer', 1],
                ['Sludge Layer', 1],
            ]],
            ['2', 'Tank Condition', [
                ['Absorption Field / Soakaway Present', 1],
                ['No Signs of Leakage / Seepage Around Tank', 1],
                ['No Signs of Overflow or Surface Ponding', 1],
                ['No Structural Cracks / Damage Visible', 1],
                ['No Odour Detected Near Tank', 1],
            ]],
            ['3', 'Assessment Recorded', [
                ['Condition of absorption field recorded?', 1],
                ['Effluent disposal method recorded?', 1],
                ['Tank adequacy for population served assessed?', 1],
            ]],
        ];
    }
}