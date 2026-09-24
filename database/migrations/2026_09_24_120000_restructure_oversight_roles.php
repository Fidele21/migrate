<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Retires Lead Inspector and adds the three oversight roles.
 *
 * Carried in a migration rather than left to the seeders because a seeder
 * only ever adds: removing a role and rehoming whoever holds it has to be
 * done explicitly, and has to happen on the server as well as here.
 *
 * The three new roles are given the Chief Inspector's reach with final
 * approval withheld, and the Mayors alone may approve a notice that closes
 * a premises.
 *
 * What they receive is read from the Chief Inspector role as it actually
 * stands, rather than from a list written here. Permissions on the live
 * platform have drifted from RolePermissionSeeder - fine.adjust and
 * road.manage are granted by separate seeders, and fine.suggest is named in
 * the seeder but checked by no code at all. Copying the live role keeps
 * "what the Chief Inspector does" true wherever this runs, instead of
 * granting a dead permission and missing two real ones.
 */
return new class extends Migration
{
    /** The one thing the Chief Inspector holds that these roles must not. */
    private const WITHHELD = 'document.approve';

    /** Approving a notice that shuts a premises. The Mayors' signature. */
    private const CLOSURE_APPROVAL = 'document.approve.closure';

    private const MAYORS = ['Lord Mayor', 'Vice Mayor'];

    private const ALL_NEW = ['DEA', 'Lord Mayor', 'Vice Mayor'];

    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        Permission::findOrCreate(self::CLOSURE_APPROVAL, 'web');
        $registrar->forgetCachedPermissions();

        $oversight = $this->chiefInspectorReach();

        foreach (self::ALL_NEW as $name) {
            $grants = $oversight;

            if (in_array($name, self::MAYORS, true)) {
                $grants[] = self::CLOSURE_APPROVAL;
            }

            Role::findOrCreate($name, 'web')->syncPermissions(array_unique($grants));
        }

        $this->retireLeadInspector();

        $registrar->forgetCachedPermissions();
    }

    /**
     * Everything the Chief Inspector may do, less the final approval.
     *
     * Falls back to nothing if that role is absent, which would mean a
     * database this migration has no business guessing about - the roles
     * are still created, just empty, and seeding fills them.
     */
    private function chiefInspectorReach(): array
    {
        $chief = Role::where('name', 'Chief Inspector')->where('guard_name', 'web')->first();

        if (! $chief) {
            return [];
        }

        return $chief->permissions
            ->pluck('name')
            ->reject(fn (string $p) => $p === self::WITHHELD)
            ->values()
            ->all();
    }

    /**
     * Anyone still holding Lead Inspector becomes an Inspector, which is the
     * closest surviving field role. Done before the role goes, so that no
     * account is ever left without one - a user with no role has no
     * permissions at all and would silently lose access.
     */
    private function retireLeadInspector(): void
    {
        $lead = Role::where('name', 'Lead Inspector')->where('guard_name', 'web')->first();

        if (! $lead) {
            return;
        }

        $inspector = Role::findOrCreate('Inspector', 'web');

        $holders = DB::table('model_has_roles')
            ->where('role_id', $lead->id)
            ->where('model_type', \App\Models\User::class)
            ->pluck('model_id');

        foreach ($holders as $id) {
            // An assignment can outlive the account it belonged to. Rehome
            // the ones that still have a user and drop the rest, rather
            // than leaving rows pointing at nothing.
            $exists = DB::table('users')->where('id', $id)->exists();

            if ($exists && ! DB::table('model_has_roles')
                    ->where(['role_id' => $inspector->id, 'model_id' => $id,
                             'model_type' => \App\Models\User::class])->exists()) {
                DB::table('model_has_roles')->insert([
                    'role_id'    => $inspector->id,
                    'model_id'   => $id,
                    'model_type' => \App\Models\User::class,
                ]);
            }
        }

        DB::table('model_has_roles')->where('role_id', $lead->id)->delete();
        DB::table('role_has_permissions')->where('role_id', $lead->id)->delete();

        $lead->delete();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        /* Lead Inspector was an Inspector with district-wide sight. Built
           from the Inspector role that survives, so this cannot fail on a
           database whose permission names differ from the seeder's. */
        $inspector = Role::where('name', 'Inspector')->where('guard_name', 'web')->first();

        $restored = $inspector ? $inspector->permissions->pluck('name')->all() : [];

        foreach (['inspection.edit.district', 'inspection.view.district', 'audit.view.district'] as $p) {
            Permission::findOrCreate($p, 'web');
            $restored[] = $p;
        }

        $registrar->forgetCachedPermissions();

        Role::findOrCreate('Lead Inspector', 'web')->syncPermissions(array_unique($restored));

        foreach (self::ALL_NEW as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();

            if ($role) {
                DB::table('model_has_roles')->where('role_id', $role->id)->delete();
                DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
                $role->delete();
            }
        }

        $registrar->forgetCachedPermissions();
    }
};
