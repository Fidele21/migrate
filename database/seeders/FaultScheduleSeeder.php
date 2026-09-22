<?php

namespace Database\Seeders;

use App\Models\Fault;
use App\Models\FaultSanction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The administrative sanctions schedule for construction faults.
 *
 * Taken from the Urban Planning Code. Twenty-two faults, fifty-two
 * sanctions, because most vary by building category: the same
 * development started without a permit costs 300,000 in Category 2 and
 * 7,000,000 in Category 5.
 *
 * Three faults carry no fine. Unauthorised development that does not
 * comply is removed at the defaulter's cost; a permit issued against the
 * planning documents is removed at the issuing authority's cost; and
 * construction threatening stability is suspended pending an audit.
 * Those are not omissions — the sanction is the action, not a payment.
 *
 * Re-running replaces the schedule. Sanctions already recorded against an
 * inspection keep their own copy of the amount and the action, so an
 * amendment here does not alter what was owed on the day.
 */
class FaultScheduleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $n = 0;

            foreach ($this->schedule() as $order => $f) {
                $fault = Fault::updateOrCreate(
                    ['type_code' => 'construction', 'code' => $f['code']],
                    ['title' => $f['title'], 'sort_order' => $order + 1, 'is_active' => true]
                );

                $fault->sanctions()->delete();

                foreach ($f['sanctions'] as $s) {
                    FaultSanction::create([
                        'fault_id'   => $fault->id,
                        'scope_kind' => $s['kind'],
                        'scope'      => $s['scope'],
                        'liable'     => $s['liable'],
                        'amount'     => $s['amount'],
                        'action'     => $s['action'],
                    ]);
                    $n++;
                }
            }

            $this->command->info(Fault::where('type_code', 'construction')->count()
                . " faults, {$n} sanctions.");
        });
    }

    /** The schedule as published. */
    private function schedule(): array
    {
        return [
            [
                'code'  => '1',
                'title' => 'New Development started without permit, but complying with urban planning and building regulations requirements',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 2',
                        'liable' => 'Defaulter',
                        'amount' => 300000,
                        'action' => 'FRW 300,000 and request for permit; suspension until authorization granted',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 3',
                        'liable' => 'Defaulter',
                        'amount' => 1000000,
                        'action' => 'FRW 1,000,000 and request for permit; suspension until authorization granted',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4',
                        'liable' => 'Defaulter',
                        'amount' => 3000000,
                        'action' => 'FRW 3,000,000 and request for permit; suspension until authorization granted',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 5',
                        'liable' => 'Defaulter',
                        'amount' => 7000000,
                        'action' => 'FRW 7,000,000 and request for permit; suspension until authorization granted',
                    ],
                ],
            ],
            [
                'code'  => '2',
                'title' => 'New development started without permit, not complying with urban planning and/or building regulations requirements',
                'sanctions' => [
                    [
                        'kind'   => 'all',
                        'scope'  => 'All Categories',
                        'liable' => 'Defaulter',
                        'amount' => null,
                        'action' => 'Removal at cost of defaulter',
                    ],
                ],
            ],
            [
                'code'  => '3',
                'title' => 'Rehabilitation (with or without structural alteration) development started without permit',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 2',
                        'liable' => 'Defaulter',
                        'amount' => 300000,
                        'action' => 'FRW 300,000 and request for permit; suspension until authorization granted',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 3',
                        'liable' => 'Defaulter',
                        'amount' => 1000000,
                        'action' => 'FRW 1,000,000 and request for permit; suspension until authorization granted',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4',
                        'liable' => 'Defaulter',
                        'amount' => 3000000,
                        'action' => 'FRW 3,000,000 and request for permit; suspension until authorization granted',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 5',
                        'liable' => 'Defaulter',
                        'amount' => 7000000,
                        'action' => 'FRW 7,000,000 and request for permit; suspension until Permit issued',
                    ],
                ],
            ],
            [
                'code'  => '4',
                'title' => 'Development without requesting the mandatory foundation inspection',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 3',
                        'liable' => 'Permittee',
                        'amount' => 300000,
                        'action' => 'FRW 300,000 and suspension until payment',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4',
                        'liable' => 'Permittee',
                        'amount' => 1000000,
                        'action' => 'FRW 1,000,000 and suspension until payment',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 5',
                        'liable' => 'Permittee',
                        'amount' => 2000000,
                        'action' => 'FRW 2,000,000 and suspension until payment',
                    ],
                ],
            ],
            [
                'code'  => '5',
                'title' => 'Occupation without permit',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 3',
                        'liable' => 'Defaulter',
                        'amount' => 500000,
                        'action' => 'FRW 500,000 and request for permit; suspension of new activities until permit issued',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4',
                        'liable' => 'Defaulter',
                        'amount' => 2000000,
                        'action' => 'FRW 2,000,000 and request for permit; suspension until permit issued',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 5',
                        'liable' => 'Defaulter',
                        'amount' => 2500000,
                        'action' => 'FRW 2,500,000 and request for permit; suspension until permit issued',
                    ],
                ],
            ],
            [
                'code'  => '6',
                'title' => 'Change of building use without permit',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 2',
                        'liable' => null,
                        'amount' => 50000,
                        'action' => 'FRW 50,000 and suspension of new activities until permit issued',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 3',
                        'liable' => null,
                        'amount' => 500000,
                        'action' => 'FRW 500,000 and suspension of new activities until permit issued',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4',
                        'liable' => null,
                        'amount' => 2000000,
                        'action' => 'FRW 2,000,000 and request for permit; suspension of new activities until permit issued',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 5',
                        'liable' => null,
                        'amount' => 2500000,
                        'action' => 'FRW 2,500,000 and request for permit; suspension of new activities until permit issued',
                    ],
                ],
            ],
            [
                'code'  => '7',
                'title' => 'Demolition without permit',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4',
                        'liable' => null,
                        'amount' => 2000000,
                        'action' => 'FRW 2,000,000 and request for permit; suspension until permit issued',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 5',
                        'liable' => null,
                        'amount' => 5000000,
                        'action' => 'FRW 5,000,000 and request for permit; suspension until permit issued',
                    ],
                ],
            ],
            [
                'code'  => '8',
                'title' => 'Construction without certified architect and/or engineer',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 3',
                        'liable' => 'Permittee',
                        'amount' => 300000,
                        'action' => 'FRW 300,000, suspension of building activities until compliance',
                    ],
                ],
            ],
            [
                'code'  => '9',
                'title' => 'Construction without contractor and supervisor',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4 and 5',
                        'liable' => 'Permittee',
                        'amount' => 5000000,
                        'action' => 'FRW 5,000,000 and request for permit; suspension until compliance',
                    ],
                ],
            ],
            [
                'code'  => '10',
                'title' => 'Building activities with expired permit and no request for renewal approved',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 2',
                        'liable' => 'Permittee',
                        'amount' => 50000,
                        'action' => 'FRW 50,000 and request for permit renewal; suspension until authorization granted',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 3',
                        'liable' => 'Permittee',
                        'amount' => 500000,
                        'action' => 'FRW 500,000 and request for permit renewal; suspension until authorization granted',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4',
                        'liable' => 'Permittee',
                        'amount' => 2000000,
                        'action' => 'FRW 2,000,000 and request for permit renewal; suspension until authorization granted',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 5',
                        'liable' => 'Permittee',
                        'amount' => 2500000,
                        'action' => 'FRW 2,500,000 and request for permit renewal; suspension until authorization granted',
                    ],
                ],
            ],
            [
                'code'  => '11',
                'title' => 'Building permit violating the provisions land use, rural and urban planning documents',
                'sanctions' => [
                    [
                        'kind'   => 'all',
                        'scope'  => 'All categories',
                        'liable' => 'Issuing authority',
                        'amount' => null,
                        'action' => 'Removal at the cost of the issuing authority with possible compensation to the permittee after assessment of the case',
                    ],
                ],
            ],
            [
                'code'  => '12',
                'title' => 'Construction violating safety and stability of the building',
                'sanctions' => [
                    [
                        'kind'   => 'all',
                        'scope'  => 'All categories',
                        'liable' => 'Defaulter',
                        'amount' => null,
                        'action' => 'Immediate Suspension of construction activities; Correction of defects or removal based on building assessment Audit report',
                    ],
                ],
            ],
            [
                'code'  => '13',
                'title' => 'Incompliance (deviation) of any approved design of the development with the approved building plans',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 2',
                        'liable' => 'Permittee',
                        'amount' => 50000,
                        'action' => 'FRW 50,000 and immediate correction',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 3',
                        'liable' => 'Permittee',
                        'amount' => 500000,
                        'action' => 'FRW 500,000 and immediate correction, or request for permit to modify; suspension until compliance',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4',
                        'liable' => 'Permittee',
                        'amount' => 2000000,
                        'action' => 'FRW 2,000,000 and immediate correction, or request for permit to modify; suspension until compliance',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 5',
                        'liable' => 'Permittee',
                        'amount' => 2500000,
                        'action' => 'FRW 2,500,000 and immediate correction, or request for permit to modify; suspension until compliance',
                    ],
                ],
            ],
            [
                'code'  => '14',
                'title' => 'Incompliance with equipment and facilities related to fire safety',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Public use buildings Category 4 and 5',
                        'liable' => null,
                        'amount' => 2000000,
                        'action' => 'FRW 2,000,000 and obligation to comply',
                    ],
                ],
            ],
            [
                'code'  => '15',
                'title' => 'Incompliance with security equipment',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Public use buildings category 4 and 5',
                        'liable' => null,
                        'amount' => 2000000,
                        'action' => 'FRW 2,000,000 and obligation to comply',
                    ],
                ],
            ],
            [
                'code'  => '16',
                'title' => 'Construction of a building without insurances',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 3',
                        'liable' => null,
                        'amount' => 500000,
                        'action' => 'FRW 500,000 and obligation to comply',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4 and 5',
                        'liable' => null,
                        'amount' => 2000000,
                        'action' => 'FRW 2,000,000 and obligation to comply',
                    ],
                ],
            ],
            [
                'code'  => '17',
                'title' => 'Building operating without insurances',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4 and 5',
                        'liable' => null,
                        'amount' => 2000000,
                        'action' => 'FRW 2,000,000 and obligation to comply',
                    ],
                ],
            ],
            [
                'code'  => '18',
                'title' => 'Incompliance with the instructions of inspectors',
                'sanctions' => [
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 2',
                        'liable' => 'Permittee or defaulter',
                        'amount' => 50000,
                        'action' => 'FRW 50,000 and obligation to comply',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 3',
                        'liable' => 'Permittee or defaulter',
                        'amount' => 500000,
                        'action' => 'FRW 500,000 and obligation to comply',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 4',
                        'liable' => 'Permittee or defaulter',
                        'amount' => 2000000,
                        'action' => 'FRW 2,000,000 and obligation to comply',
                    ],
                    [
                        'kind'   => 'building_category',
                        'scope'  => 'Category 5',
                        'liable' => 'Permittee or defaulter',
                        'amount' => 2500000,
                        'action' => 'FRW 2,500,000 and obligation to comply',
                    ],
                ],
            ],
            [
                'code'  => '19',
                'title' => 'Obstruction of inspector on duty',
                'sanctions' => [
                    [
                        'kind'   => 'all',
                        'scope'  => 'All categories',
                        'liable' => 'Permittee or defaulter',
                        'amount' => 200000,
                        'action' => 'FRW 200,000 and obligation to comply',
                    ],
                ],
            ],
            [
                'code'  => '20',
                'title' => 'Failure to display construction site signage',
                'sanctions' => [
                    [
                        'kind'   => 'all',
                        'scope'  => 'All categories',
                        'liable' => 'Permittee or defaulter',
                        'amount' => 50000,
                        'action' => 'FRW 50,000 and obligation to comply',
                    ],
                ],
            ],
            [
                'code'  => '21',
                'title' => 'Unauthorized construction activities that interrupt public road usage on roads as categorized by UPC, or which encroach beyond the plot boundary without prior permission',
                'sanctions' => [
                    [
                        'kind'   => 'road_class',
                        'scope'  => 'Primary distributor road',
                        'liable' => 'Defaulter',
                        'amount' => 200000,
                        'action' => 'FRW 200,000 and immediate stop and reversal of condition',
                    ],
                    [
                        'kind'   => 'road_class',
                        'scope'  => 'Secondary distributor road',
                        'liable' => 'Defaulter',
                        'amount' => 100000,
                        'action' => 'FRW 100,000 and immediate stop and reversal of condition',
                    ],
                    [
                        'kind'   => 'road_class',
                        'scope'  => 'Local distributor road',
                        'liable' => 'Defaulter',
                        'amount' => 50000,
                        'action' => 'FRW 50,000 and immediate stop and reversal of condition',
                    ],
                    [
                        'kind'   => 'road_class',
                        'scope'  => 'Access road',
                        'liable' => 'Defaulter',
                        'amount' => 50000,
                        'action' => 'FRW 50,000 and immediate stop and reversal of condition',
                    ],
                ],
            ],
            [
                'code'  => '22',
                'title' => 'Prohibited activity as defined by UPC in environmentally sensitive areas other than construction of a building',
                'sanctions' => [
                    [
                        'kind'   => 'sensitive_area',
                        'scope'  => 'Flood plains',
                        'liable' => null,
                        'amount' => 100000,
                        'action' => '100,000 and immediate stop and reversal of condition',
                    ],
                    [
                        'kind'   => 'sensitive_area',
                        'scope'  => 'Wetlands',
                        'liable' => null,
                        'amount' => 100000,
                        'action' => '100,000 and immediate stop and reversal of condition',
                    ],
                    [
                        'kind'   => 'sensitive_area',
                        'scope'  => 'Steep slopes, ridgelines and hilltops',
                        'liable' => null,
                        'amount' => 200000,
                        'action' => '200,000 and immediate stop and reversal of condition',
                    ],
                    [
                        'kind'   => 'sensitive_area',
                        'scope'  => 'Natural open space',
                        'liable' => null,
                        'amount' => 50000,
                        'action' => '50,000 and immediate stop and reversal of condition',
                    ],
                ],
            ],
        ];
    }
}
