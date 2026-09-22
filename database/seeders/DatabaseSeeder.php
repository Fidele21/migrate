<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Every seeder in the project, in dependency order.
     *
     * The order is not cosmetic, and getting it wrong fails quietly
     * rather than loudly. Users assigned to roles that do not yet exist
     * simply end up with no role, and district scoping then shows them
     * the whole city instead of their district.
     *
     * A class listed here but absent from the codebase is reported and
     * skipped rather than throwing, so a partial checkout still seeds
     * whatever it has.
     */
    private const SEEDERS = [
        /* Foundation. Districts first - users carry a district_id, and
           district scoping is what limits what an officer can see. */
        DistrictSeeder::class,

        /* Access control. Roles and permissions before anyone is given
           one. RoadPermission and FineSecretary attach further
           permissions to roles the first seeder creates. */
        RolePermissionSeeder::class,
        RoadPermissionSeeder::class,
        FineSecretarySeeder::class,

        /* Accounts, once both districts and roles exist. */
        UserSeeder::class,

        /* Checklists. Each creates a versioned template with its
           sections and weighted items, in the order they were written.

           ChecklistImportSeeder is deliberately absent: it copies the
           building and petrol checklists out of the old cPanel database,
           which v1 does not ship with. Run it by hand once LEGACY_DB_*
           points somewhere real. */
        BuildingChecklistV2Seeder::class,
        ConstructionChecklistSeeder::class,
        DeskReviewChecklistSeeder::class,
        WastewaterChecklistSeeder::class,

        /* Sanctions last. Faults reference checklist items, so the
           checklists have to be in place before the schedule that
           prices them. */
        FaultScheduleSeeder::class,
        FineAdjustSeeder::class,
    ];

    public function run(): void
    {
        $ran = 0;

        foreach (self::SEEDERS as $seeder) {
            if (! class_exists($seeder)) {
                $this->command->warn("  skipped, not found: {$seeder}");
                continue;
            }

            $this->call($seeder);
            $ran++;
        }

        $this->command->info("Seeded {$ran} of " . count(self::SEEDERS) . ".");
    }
}