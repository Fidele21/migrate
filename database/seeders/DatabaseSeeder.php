<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Every seeder in the project, in dependency order.
     *
     * The order is not cosmetic, and getting it wrong fails quietly
     * rather than loudly. A user assigned to a role that does not yet
     * exist simply ends up with no role, and district scoping then
     * shows that officer the whole city instead of their district.
     *
     * A class listed here but absent - or named differently from its
     * file - is reported and skipped rather than throwing, so one bad
     * name does not abandon the run half-finished.
     */
    private const SEEDERS = [

        /* Foundation. Districts first: users carry a district_id, and
           that assignment is what limits what an officer can see. */
        DistrictSeeder::class,

        /* Access control. Roles and permissions before anybody is given
           one. The two that follow attach further permissions to roles
           the first seeder creates. */
        RolePermissionSeeder::class,
        RoadPermissionSeeder::class,
        FineSecretarySeeder::class,

        /* Accounts, once both districts and roles exist. */
        UserSeeder::class,

        /* Checklists. Each creates a versioned template with its
           sections and weighted items. */
        ChecklistImportSeeder::class,
        BuildingChecklistV2Seeder::class,
        ConstructionChecklistSeeder::class,
        DeskReviewChecklistSeeder::class,
        WastewaterChecklistSeeder::class,

        /* Sanctions last. Faults reference checklist items, so the
           checklists must exist before the schedule that prices them. */
        FaultScheduleSeeder::class,
        FineAdjustSeeder::class,
    ];

    public function run(): void
    {
        /* Spatie caches the permission collection, and a freshly
           created permission is invisible to syncPermissions() until
           that cache is dropped. This is the cause of "There is no
           permission named inspection.create for guard web" on a clean
           database - the rows exist, the lookup is stale.

           Cleared before the run and after every seeder, because each
           one may add roles or permissions the next depends on. */
        $this->forgetPermissionCache();

        $ran     = 0;
        $skipped = [];

        foreach (self::SEEDERS as $seeder) {
            if (! class_exists($seeder)) {
                $skipped[] = class_basename($seeder);
                continue;
            }

            $this->call($seeder);
            $this->forgetPermissionCache();
            $ran++;
        }

        $this->command->newLine();
        $this->command->info("Seeded {$ran} of " . count(self::SEEDERS) . '.');

        if ($skipped) {
            $this->command->warn('Not found, skipped: ' . implode(', ', $skipped));
        }
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}