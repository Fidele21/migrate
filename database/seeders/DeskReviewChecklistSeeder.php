<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Models\ChecklistTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Desk review — seven checklists, one for each kind of application.
 *
 * A desk review is not a site visit. Nobody goes anywhere: an officer
 * reads what was submitted with a permit application and judges whether
 * it is complete and sound. The premises may not exist yet.
 *
 * The seven use the stage mechanism built for construction, because the
 * shape is the same: one category, several checklists, the officer
 * choosing which applies.
 *
 * Where the spreadsheet left an item unweighted, a weight was agreed
 * rather than left at zero. An item worth nothing is one an applicant
 * can fail for free, and the unweighted items were the consequential
 * ones — whether the as-built matches the approved drawings, whether a
 * structural audit was submitted.
 *
 * RWO weighted its two structural items below RWI's, which involves no
 * structural alteration at all. Read as an error in the sheet and
 * corrected, which is also what brings RWO to 100.
 */
class DeskReviewChecklistSeeder extends Seeder
{
    private const TYPES = [
        'ncp' => 'New Construction Permit',
        'op' => 'Occupation Permit',
        'renew' => 'Permit Renewal',
        'mod' => 'Modification Permit',
        'rwo' => 'Refurbishment With Structural Alteration',
        'fence' => 'Fence Permit',
        'rwi' => 'Refurbishment Without Structural Alteration',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::TYPES as $stage => $name) {
                $this->publish($stage, $name, $this->sections()[$stage]);
            }
        });
    }

    private function publish(string $stage, string $name, array $sections): void
    {
        $next = (int) ChecklistTemplate::where('type_code', 'desk_review')
            ->where('stage', $stage)->max('version') + 1;

        $template = ChecklistTemplate::create([
            'type_code'    => 'desk_review',
            'stage'        => $stage,
            'name'         => 'Desk Review — ' . $name,
            'version'      => $next,
            'published_at' => now(),
        ]);

        foreach ($sections as $order => $sec) {
            $section = ChecklistSection::create([
                'template_id'    => $template->id,
                'section_number' => (string) ($order + 1),
                'title'          => $sec[0],
                'sort_order'     => $order + 1,
            ]);

            foreach ($sec[1] as $i => $item) {
                ChecklistItem::create([
                    'section_id'     => $section->id,
                    'item_code'      => ($order + 1) . '.' . ($i + 1),
                    'label'          => $item[0],
                    'max_score'      => $item[1],
                    'allows_na'      => true,
                    'is_prohibition' => false,
                    'sort_order'     => $i + 1,
                ]);
            }
        }

        $points = collect($sections)->flatMap(fn ($s) => $s[1])->sum(fn ($i) => $i[1]);
        $items  = collect($sections)->sum(fn ($s) => count($s[1]));

        $this->command->info(str_pad($stage, 8) . "v{$next}  "
            . str_pad(count($sections) . ' sections', 14)
            . str_pad($items . ' items', 12) . $points . ' points');
    }

    /** The seven checklists, as agreed with the inspection unit. */
    private function sections(): array
    {
        return [

            'ncp' => [
                ['Documentation submitted', [
                    ['A project brief including a description of methods and techniques applied, except for buildings in category 2;', 5],
                    ['detailed site plan and landscaping', 5],
                    ['A copy of geotechnical report, except for buildings in category 2 and category 3;', 5],
                    ['copy of environmental impact assessment documents, except for buildings in category 2 and 3;', 5],
                    ['architectural drawings;', 5],
                    ['mechanical, electrical, sanitation, internet broadband connectivity plans and fire safety except for buildings in category 2;', 5],
                    ['structural design: comprising plans and calculations, except for buildings in category 2;', 5],
                    ['bill of quantities and cost estimate, except for buildings in category 2;', 5],
                    ['certification of works signed by a certified architect or engineer, except for building category 2( RHA FORM 4)', 5],
                ]],
                ['Context', [
                    ['Is there a Physical Plan?', 3],
                    ['What is its Zoning?', 3],
                    ['what is the Building Category?', 3],
                    ['Is the building category complying to the zoning regulation?', 3],
                    ['Is the permit comply to the zoning? If applicable', 3],
                    ['Compliance dwelling Units (Minimum, maximum)', 3],
                ]],
                ['Topography', [
                    ['What is the Slope? (Plot is not in high-risk zone?)', 3],
                    ['Sections (Perspective with respect to the land form)', 3],
                    ['Is Retaining wall & Soil (Stability) checked in the design?', 3],
                ]],
                ['Environment', [
                    ['Is site Plan considered after land readjustment?', 3],
                ]],
                ['Circulation', [
                    ['Is the plot Accessible', 2],
                    ['Is Parking enough?', 2],
                ]],
                ['WW Management', [
                    ['Is Sewage treatment Plant provided?', 2],
                    ['Septic tank in 3 m from the boundary', 2],
                    ['Soak Pit should be in 3 m from the boundary', 2],
                ]],
                ['Electrical installation', [
                    ['Selection of equipment and loading', 2],
                    ['Color code', 2],
                ]],
                ['General assessment', [
                    ['Building coverage', 2],
                    ['Engineer & Ref', 2],
                    ['Foundation details are in the drawing and design?', 2],
                    ['Drawings (Site Plan, Sections area clearly detailed?) write what is missing in suggestion', 3],
                    ['Is the feedback given to the applicant relevant and consistency', 2],
                ]],
            ],

            'op' => [
                ['Documentation submitted', [
                    ['as built drawings;', 10],
                    ['updated bill of quantities, except for buildings in category 2;', 10],
                    ['photos showing the current progress of construction works of the building.', 10],
                    ['material and equipments specifications;', 10],
                    ['a copy of the previously granted building permit;', 10],
                    ['As built matches with approved drawings?', 15],
                    ['maintenance plan of the building.', 10],
                    ['Fire fighting equipment are they installed collectly?', 10],
                    ['Penalities are payed if any?', 3],
                    ['Site Plan was respected?', 8],
                ]],
                ['General assessment', [
                    ['Foundation details are in the drawing and design?', 2],
                    ['Is the feedback given to the applicant relevant and consistency', 2],
                ]],
            ],

            'renew' => [
                ['Documentation submitted', [
                    ['a copy of the expired building permit;', 10],
                    ['approved drawings of the building;', 10],
                    ['photos showing the current progress of construction works of the building.', 15],
                    ['is there the mistakes in old permitting, that can lead to the cancelation of the permit?', 65],
                ]],
            ],

            'mod' => [
                ['Documentation submitted', [
                    ['a copy of existing building permit', 5],
                    ['approved drawings of the building', 5],
                    ['design and drawings of new modified project', 10],
                    ['photos showing the current progress of construction works of the building', 5],
                ]],
                ['Context', [
                    ['Is the modification not brock the access to the neigborhood?', 10],
                    ['Is the modification not violating the zoning regulation?', 10],
                    ['is a modification report submitted reflecting to the changes?', 10],
                    ['Detailed Structural design is submitted', 10],
                    ['Modification permit is not affecting the approved site Plan', 10],
                    ['Is the structural Audit report submitted where is applicable?', 15],
                ]],
                ['General assessment', [
                    ['Is the feedback given to the applicant relevant and consistency', 10],
                ]],
            ],

            'rwo' => [
                ['Documentation submitted', [
                    ['a project brief including description of methods and techniques applied, except for buildings in category 2;', 10],
                    ['mechanical, electrical, sanitation, internet broadband connectivity and fire safety plans, except for buildings in category 2;', 10],
                    ['photos showing the building to be refurbished.', 15],
                ]],
                ['Context', [
                    ['Is there a Physical Plan?', 3],
                    ['What is its Zoning?', 3],
                    ['what is the Building Category?', 3],
                    ['Is the building category complying to the zoning regulation?', 3],
                    ['Is the physical plan complying to the zoning? If applicable', 3],
                    ['Compliance dwelling Units (Minimum, maximum)', 3],
                ]],
                ['Topography', [
                    ['What is the Slope? (Plot is not in high-risk zone?)', 3],
                    ['Sections (Perspective with respect to the land form)', 3],
                    ['Is Retaining wall & Soil (Stability) checked in the design?', 6],
                ]],
                ['Environment', [
                    ['Is site Plan considered after land readjustment?', 2],
                ]],
                ['Circulation', [
                    ['Is the plot Accessible', 2],
                    ['Is Parking enough?', 2],
                ]],
                ['WW Management', [
                    ['Is Sewage treatment Plant provided?', 2],
                    ['Septic tank', 2],
                    ['Soak Pit should be in 3 m from the boundary', 2],
                ]],
                ['Electrical installation', [
                    ['Selection of equipment and loading', 2],
                    ['Color code', 2],
                ]],
                ['General assessment', [
                    ['Architecture & Ref', 2],
                    ['Engineer & Ref', 5],
                    ['Foundation details are in the drawing and design?', 8],
                    ['Drawings (Site Plan, Sections area clearly detailed?) write what is missing in suggestion', 2],
                    ['Is the feedback given to the applicant relevant and consistency', 2],
                ]],
            ],

            'fence' => [
                ['Documentation submitted', [
                    ['Are Architectural drawings and perspectives of fence provided?', 10],
                    ['Is detailed site plan provided?', 10],
                    ['Have the photos of the location where the fence will be constructed been provided?', 15],
                    ['Detailed structural design for retaining wall is submitted (where Applicabel)', 25],
                    ['What is its Zoning?', 10],
                    ['Is the Existing Building meet with zoning regulation?', 10],
                ]],
                ['Circulation', [
                    ['Is the fence not brocking the Accessiblity for neigborhood?', 10],
                ]],
                ['General assessment', [
                    ['Is the feedback given to the applicant relevant and consistency', 10],
                ]],
            ],

            'rwi' => [
                ['Documentation submitted', [
                    ['A project brief including a description of methods and techniques applied, except for buildings in category 2;', 5],
                    ['site plan and landscaping;', 5],
                    ['A copy of geotechnical report, except for buildings in category 2 and category 3;', 5],
                    ['copy of environmental impact assessment documents, except for buildings in category 2 and 3;', 5],
                    ['architectural drawings;', 5],
                    ['mechanical, electrical, sanitation, internet broadband connectivity plans and fire safety except for buildings in category 2;', 5],
                    ['structural design comprising plans and calculations, except for buildings in category 2;', 5],
                    ['bill of quantities and cost estimate, except for buildings in category 2;', 5],
                    ['Is the refurbishment greater than 20%?', 3],
                ]],
                ['Context', [
                    ['Is there a Physical Plan?', 3],
                    ['What is its Zoning?', 3],
                    ['what is the Building Category?', 3],
                    ['Is the building category complying to the zoning regulation?', 3],
                    ['Is the physical plan complying to the zoning? If applicable', 3],
                    ['Compliance dwelling Units (Minimum, maximum)', 3],
                ]],
                ['Topography', [
                    ['What is the Slope? (Plot is not in high-risk zone?)', 3],
                    ['Sections (Perspective with respect to the land form)', 3],
                    ['Is Retaining wall & Soil (Stability) checked in the design?', 3],
                ]],
                ['Environment', [
                    ['Is site Plan considered after land readjustment?', 3],
                ]],
                ['Circulation', [
                    ['Is the plot Accessible', 2],
                    ['Is Parking enough?', 2],
                ]],
                ['WW Management', [
                    ['Is Sewage treatment Plant provided?', 2],
                    ['Septic tank', 2],
                    ['Soak Pit should be in 3 m from the boundary', 2],
                ]],
                ['Electrical installation', [
                    ['Selection of equipment and loading', 2],
                    ['Color code', 2],
                ]],
                ['General assessment', [
                    ['Architecture & Ref', 2],
                    ['Engineer & Ref', 2],
                    ['Foundation details are in the drawing and design?', 5],
                    ['Drawings (Site Plan, Sections area clearly detailed?) write what is missing in suggestion', 2],
                    ['Is the feedback given to the applicant relevant and consistency', 2],
                ]],
            ],
        ];
    }
}