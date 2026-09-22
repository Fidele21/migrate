<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'GASABO',     'name' => 'Gasabo'],
            ['code' => 'KICUKIRO',   'name' => 'Kicukiro'],
            ['code' => 'NYARUGENGE', 'name' => 'Nyarugenge'],
        ] as $d) {
            District::updateOrCreate(['code' => $d['code']], $d);
        }
    }
}
