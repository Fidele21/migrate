<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Models\ChecklistTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ongoing construction — two checklists, one for each stage.
 *
 * Substructure work is finished and buried before superstructure begins,
 * so the two cannot be assessed on one visit. The inspector chooses the
 * stage when starting and the platform serves the matching checklist.
 *
 * The weights below are a first proposal, set by consequence rather than
 * by count: a trench that may collapse carries more than a site office.
 * The unit should review them before they underpin enforcement.
 *
 * Statutory Compliance carries 20 in both stages. Whether the works match
 * the approved drawings, and whether the permit is valid, are the two
 * questions a construction inspection exists to answer — everything else
 * assumes the building is allowed to be there at all.
 */
class ConstructionChecklistSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->publish('substructure', 'Ongoing Construction — Substructure', $this->substructure());
            $this->publish('superstructure', 'Ongoing Construction — Superstructure', $this->superstructure());
        });
    }

    private function publish(string $stage, string $name, array $sections): void
    {
        $next = (int) ChecklistTemplate::where('type_code', 'construction')
            ->where('stage', $stage)->max('version') + 1;

        $template = ChecklistTemplate::create([
            'type_code'    => 'construction',
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

        $this->command->info(ucfirst($stage) . " v{$next} — "
            . count($sections) . " sections, {$items} items, {$points} points.");
    }


    private function substructure(): array
    {
        return [
            ['1', 'Statutory Compliance', [
                ['Physical plan exists for the site', 3],
                ['Building category complies with the zoning', 5],
                ['Construction permit valid and not expired', 6],
                ['Works comply with the approved drawings', 6],
            ]],
            ['2', 'Foundation and Structural Works', [
                ['Detailed site plan on site — setbacks and road reserve verified', 4],
                ['Soil condition assessed and stabilisation carried out', 4],
                ['Trench depth and width measured against the approved drawings', 4],
                ['Timbering or shoring installed to prevent trench wall collapse', 5],
                ['Steel rebar layout — size, quantity and spacing of bars and stirrups', 5],
                ['Concrete cover — spacers or chairs installed as specified', 4],
                ['Blinding layer poured before steel is fixed', 3],
                ['Concrete test results available and bearing the UPI', 5],
            ]],
            ['3', 'Contractor and Supervision', [
                ['Contractor and consultant registered as declared on Form 6', 4],
                ['Safety engineer present on site', 4],
            ]],
            ['4', 'Site Safety and Facilities', [
                ['Personal protective equipment provided and worn', 6],
                ['First aid kit available and adequately stocked', 3],
                ['Site access, internal circulation and emergency signs', 3],
                ['Site office', 2],
                ['Site staff toilets', 2],
                ['Construction materials stored safely — sand, bricks, stones, gravel', 2],
                ['Holding fence and site indication sign post', 4],
                ['Site kept clean and free of obstruction', 2],
            ]],
            ['5', 'Site Documentation', [
                ['Construction permit displayed on site', 4],
                ['Site instruction book kept and current', 3],
                ['Contracts held for contractor and supervisor', 4],
                ['Insurance in force for workers', 3],
            ]],
        ];
    }

    private function superstructure(): array
    {
        return [
            ['1', 'Statutory Compliance', [
                ['Physical plan exists for the site', 3],
                ['Building category complies with the zoning', 5],
                ['Construction permit valid and not expired', 6],
                ['Works comply with the approved drawings', 6],
            ]],
            ['2', 'Approved Documents on Site', [
                ['Site plan — road reserve and setbacks', 3],
                ['Architectural plans', 3],
                ['Detailed structural drawings', 2],
                ['Mechanical, electrical and plumbing plans', 2],
            ]],
            ['3', 'Laboratory Test Reports', [
                ['Geotechnical report', 2],
                ['Compressive strength of concrete', 3],
                ['Steel reinforcement bars', 3],
                ['Steel members', 2],
                ['Timber members', 1],
                ['Aggregates — fine and coarse', 1],
            ]],
            ['4', 'Building Elements', [
                ['Walling details as approved', 3],
                ['Ceiling and roof details as approved', 3],
                ['Waste water treatment plant', 2],
                ['Facilities for persons with disabilities — lift and ramp at gradient below 1:12', 2],
            ]],
            ['5', 'Personal Protective Equipment', [
                ['Safety boots', 2],
                ['Helmets', 3],
                ['Overalls', 2],
                ['Gloves', 2],
                ['Protective gear — eye and ear', 1],
                ['Safety belts', 2],
                ['Safety ropes', 2],
            ]],
            ['6', 'Scaffolding', [
                ['Toe boards fitted', 2],
                ['Handrails fitted', 3],
                ['Fall restraints in place', 3],
                ['Ladders sound and secured', 2],
                ['Safe access stairs', 2],
            ]],
            ['7', 'Site Safety and Facilities', [
                ['Formwork with appropriate supports', 3],
                ['First aid kit available and adequately stocked', 2],
                ['Site access controlled', 1],
                ['Internal circulation clear', 1],
                ['Emergency signs displayed', 1],
                ['Site office', 1],
                ['Site staff toilets', 1],
                ['Holding fence and site indication sign post', 2],
                ['Site hygiene and cleanliness', 1],
                ['Construction materials stored safely', 1],
            ]],
            ['8', 'Site Documentation', [
                ['Site instruction book kept and current', 2],
                ['Contract held for the contractor', 2],
                ['Contract held for the supervisor', 2],
                ['Insurance in force for workers', 2],
            ]],
        ];
    }
}
