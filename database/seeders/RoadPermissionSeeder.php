<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Who maintains the road register.
 *
 * A permission of its own rather than reusing inspection.create. The
 * register is not an inspection: it has no score, no report and no
 * signature, and the officers who maintain it are not necessarily the
 * ones who inspect.
 *
 * Giving it its own name also means it can be taken away from inspectors
 * later without taking away their ability to inspect — which reusing
 * inspection.create would not allow.
 */
class RoadPermissionSeeder extends Seeder
{
    private const HOLDERS = [
        'Chief Inspector',
        'Senior Inspector',
        'Director of Inspection',
        'Inspector',
        'DEA',
        'Lord Mayor',
        'Vice Mayor',
    ];

    public function run(): void
    {
        $permission = Permission::firstOrCreate([
            'name'       => 'road.manage',
            'guard_name' => 'web',
        ]);

        $granted = [];
        $missing = [];

        foreach (self::HOLDERS as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();

            if (! $role) {
                $missing[] = $name;
                continue;
            }

            $role->givePermissionTo($permission);
            $granted[] = $name;
        }

        app()['cache']->forget('spatie.permission.cache');

        $this->command->info('road.manage granted to: ' . implode(', ', $granted));

        if ($missing) {
            /* Named rather than silently skipped: a role that does not
               exist under the expected name means either a typo here or a
               role named differently in the platform, and both matter. */
            $this->command->warn('No such role: ' . implode(', ', $missing));
            $this->command->line('Roles on record: '
                . Role::pluck('name')->implode(', '));
        }
    }
}