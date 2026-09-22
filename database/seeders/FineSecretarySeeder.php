<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The Secretary confirms a fine against the letter that issued.
 *
 * Joining the inspectors rather than replacing them. The Secretary holds
 * the register and knows what actually left the building, so is the one
 * office that can attest a fine matches the letter served.
 *
 * Because the inspectors keep the permission, a confirmation can mean two
 * things — that the amount was agreed, or that the letter was checked.
 * The confirming officer's name is recorded against it, so which is meant
 * can at least be established afterwards.
 */
class FineSecretarySeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::firstOrCreate([
            'name'       => 'fine.confirm',
            'guard_name' => 'web',
        ]);

        $role = Role::where('name', 'Secretary')->where('guard_name', 'web')->first();

        if (! $role) {
            $this->command->warn('No Secretary role. Roles on record: '
                . Role::pluck('name')->implode(', '));
            return;
        }

        $role->givePermissionTo($permission);
        app()['cache']->forget('spatie.permission.cache');

        $this->command->info('fine.confirm now held by: '
            . $permission->roles()->pluck('name')->implode(', '));
    }
}
