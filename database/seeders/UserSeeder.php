<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demonstration accounts mirroring the CoK Inspection Unit.
 *
 * All are created with an initial password and flagged to change it on
 * first sign-in, which is the same path a real account takes.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $d = District::pluck('id', 'code');

        $people = [
            ['Chief Inspector',       'chief.inspector@kigalicity.gov.rw',     null,         'Chief Inspector'],
            ['Senior Inspector',      'senior.inspector@kigalicity.gov.rw',    null,         'Senior Inspector'],
            ['Director Gasabo',       'director.gasabo@kigalicity.gov.rw',     'GASABO',     'Director of Inspection'],
            ['Director Kicukiro',     'director.kicukiro@kigalicity.gov.rw',   'KICUKIRO',   'Director of Inspection'],
            ['Director Nyarugenge',   'director.nyarugenge@kigalicity.gov.rw', 'NYARUGENGE', 'Director of Inspection'],
            ['Eugene',                'eugene@kigalicity.gov.rw',              'GASABO',     'Inspector'],
            ['Thierry',               'thierry@kigalicity.gov.rw',             'KICUKIRO',   'Inspector'],
            ['Idesbald',              'idesbald@kigalicity.gov.rw',            'NYARUGENGE', 'Inspector'],
            ['Secretary',             'secretary@kigalicity.gov.rw',           null,         'Secretary'],
            ['Recovery Officer',      'recovery@kigalicity.gov.rw',            null,         'Recovery Officer'],
            ['System Administrator',  'admin@kigalicity.gov.rw',               null,         'Administrator'],
        ];

        foreach ($people as [$name, $email, $districtCode, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'                 => $name,
                    'password'             => Hash::make('ChangeMe!2026'),
                    'district_id'          => $districtCode ? ($d[$districtCode] ?? null) : null,
                    'is_active'            => true,
                    'must_change_password' => true,
                ]
            );

            $user->syncRoles([$role]);
        }

        $this->command->info('Seeded ' . count($people) . ' accounts.');
    }
}
