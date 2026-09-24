<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Who may change what a fine is worth.
 *
 * Confirming and adjusting are different acts and now carry different
 * permissions.
 *
 * The Secretary confirms: a statement that the fine matches the letter
 * that was served. Nothing to decide, nothing to type.
 *
 * The Chief Inspector, Senior Inspector and Director may adjust or waive
 * — a decision about enforcement rather than about paperwork, and one
 * that must carry a reason.
 *
 * The Recovery Officer records payment and sees nothing that is not yet
 * owed.
 */
class FineAdjustSeeder extends Seeder
{
    private const GRANTS = [
        'fine.adjust' => ['Chief Inspector', 'Senior Inspector', 'Director of Inspection',
                           'DEA', 'Lord Mayor', 'Vice Mayor'],
    ];

    public function run(): void
    {
        foreach (self::GRANTS as $name => $roles) {
            $permission = Permission::firstOrCreate([
                'name'       => $name,
                'guard_name' => 'web',
            ]);

            $missing = [];

            foreach ($roles as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

                if (! $role) { $missing[] = $roleName; continue; }

                $role->givePermissionTo($permission);
            }

            $this->command->info($name . ' → ' . $permission->roles()->pluck('name')->implode(', '));

            if ($missing) {
                $this->command->warn('  no such role: ' . implode(', ', $missing));
            }
        }

        app()['cache']->forget('spatie.permission.cache');
    }
}