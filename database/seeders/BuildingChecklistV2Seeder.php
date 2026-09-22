<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Models\ChecklistTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The occupied buildings checklist, version 2.
 *
 * Version 1 stays published and untouched: an inspection carried out
 * under it must always reproduce the score arrived at on the day. This
 * creates a new version alongside it, and new inspections pick it up
 * because ChecklistTemplate::current() takes the highest published
 * version.
 *
 * Sections 2 and 3 total 51 and 27, as decided by the unit, so the
 * checklist sums to 97 rather than 100. Compliance is earned over
 * possible, so a premises meeting every requirement still reads 100%.
 *
 * No item here is a prohibition. Section 4 asks whether a kitchen is
 * operated safely, which is a requirement — unlike a petrol station,
 * where a kitchen is forbidden and answering yes must lose the points.
 */
class BuildingChecklistV2Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            $next = (int) ChecklistTemplate::where('type_code', 'building')->max('version') + 1;

            $template = ChecklistTemplate::create([
                'type_code'    => 'building',
                'name'         => 'Occupied Buildings — Fire Safety and Security',
                'version'      => $next,
                'published_at' => now(),
            ]);

            foreach ($this->sections() as $order => $sec) {
                $section = ChecklistSection::create([
                    'template_id'    => $template->id,
                    'section_number' => $sec['no'],
                    'title'          => $sec['title'],
                    'sort_order'     => $order + 1,
                ]);

                foreach ($sec['items'] as $i => $item) {
                    ChecklistItem::create([
                        'section_id'     => $section->id,
                        'item_code'      => $sec['no'] . '.' . ($i + 1),
                        'label'          => $item[0],
                        'max_score'      => $item[1],
                        'allows_na'      => true,
                        'is_prohibition' => false,
                        'sort_order'     => $i + 1,
                    ]);
                }
            }

            $s = $this->sections();

            $this->command->info('Template v' . $next . ' published — '
                . count($s) . ' sections, '
                . collect($s)->sum(fn ($x) => count($x['items'])) . ' items, '
                . collect($s)->flatMap(fn ($x) => $x['items'])->sum(fn ($i) => $i[1]) . ' points.');
        });
    }

    /** The checklist as agreed with the Inspection Unit. */
    private function sections(): array
    {
        return [
            [
                'no'    => '1',
                'title' => 'Legal and Regulatory Documents',
                'items' => [
                    ['Is a valid construction permit available?', 2],
                    ['Is a valid occupation permit available?', 2],
                    ['Is valid building insurance in place?', 2],
                ],
            ],
            [
                'no'    => '2',
                'title' => 'Fire Safety and Security Equipment and Signage',
                'items' => [
                    ['Are fire extinguishers provided at every 15m on each floor? (DCP, Co2, Foam)', 3],
                    ['Is an automated fire suppression system(DSPA) installed? (Dry sprinkler powder Aerosol), FM200', 2],
                    ['Are water hose reels provided?', 3],
                    ['Are fire hydrants with 45mm or 75mm Storz couplings provided?', 3],
                    ['Is a water reservoir/tank with 30 cubic meters available?', 3],
                    ['Is a fire water pump operating at 8–15 bar available?', 3],
                    ['Is a sprinkler system installed? (water Sprinkler, foam Sprinkler)', 3],
                    ['Is a fire alarm system with a control panel installed and functional?', 3],
                    ['Is a beam detection system installed and functional?', 2],
                    ['Is a heat detection system installed and functional?', 2],
                    ['Are emergency exit routes and signs clearly provided?', 2],
                    ['Are emergency evacuation and floor plans available and displayed?', 2],
                    ['Are floor/level number signs clearly displayed?', 2],
                    ['Are emergency contact numbers for police, fire brigade, and ambulance clearly displayed?', 2],
                    ['Are designated assembly areas and signs provided?', 2],
                    ['Are dust vases with shovels provided?', 2],
                    ['Are adequate PWD facilities, signs, and accessible pathways provided?', 2],
                    ['Are CCTV cameras and a control room with at least 3 months of storage capacity available?', 2],
                    ['Are adequate security screening systems provided (luggage scanners, metal detectors, and under-search mirrors?)', 2],
                    ['Are first aid boxes available and adequately equipped?', 2],
                    ['Is the facility accessible to fire and rescue services?', 2],
                    ['Are trained Security managers & staff available?', 2],
                ],
            ],
            [
                'no'    => '3',
                'title' => 'Electrical and Mechanical Installation',
                'items' => [
                    ['Is electrical installation compliance with applicable norms and standards available?', 3],
                    ['Are lightning protection systems installed and tested within the last six months?', 3],
                    ['Is the generator automatic and equipped with automatic fire suppression?', 3],
                    ['is a UPS system provided where required?', 2],
                    ['Does the elevator stop accurately at each landing level?', 3],
                    ['Are elevator controls accessible at 900–1100 mm height?', 2],
                    ['Are Braille, tactile buttons, and audible signals provided?', 3],
                    ['Is the elevator inspected every six months and properly recorded?', 5],
                    ['Are electrical diagrams for the elevator available?', 1],
                    ['Is mechanical ventilation provided where required?', 2],
                ],
            ],
            [
                'no'    => '4',
                'title' => 'Other Safety Requirements — Kitchen',
                'items' => [
                    ['Is the gas kitchen completely separated from the charcoal/wood cooking area?', 3],
                    ['Are gas cylinders stored outside,separate from where they are used?', 3],
                    ['Is the gas installation fitted with a working gas detector and shuttle?', 3],
                    ['Is a fire blanket provided and mounted in an accessible location within the kitchen?', 2],
                    ['Is there adequate ventilation to ensure indoor air quality does not exceed 75µg/m3    of particulates?', 2],
                ],
            ],
        ];
    }
}