<?php

namespace App\Console\Commands;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\District;
use App\Models\Entity;
use App\Models\Inspection;
use App\Models\InspectionAnswer;
use App\Models\InspectionTeamMember;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Brings the existing inspection records into the rebuilt platform.
 *
 * Reads through the read-only legacy connection and writes native
 * records. Safe to run more than once: everything is matched on
 * legacy_id, so a second run updates rather than duplicates.
 *
 *   php artisan legacy:migrate --dry-run
 *   php artisan legacy:migrate
 */
class MigrateLegacyData extends Command
{
    protected $signature = 'legacy:migrate
                            {--dry-run : Report what would happen without writing}
                            {--type= : Limit to one category, e.g. petrol}';

    protected $description = 'Migrate premises and inspections from the legacy database';

    private const TYPE_MAP = [1 => 'building', 2 => 'petrol'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $legacy = DB::connection('legacy');

        if ($dry) {
            $this->warn('DRY RUN — nothing will be written.');
        }

        // Checklist items are matched by code within the right template,
        // so answers land on the correct item even if IDs differ.
        $itemMap = [];
        foreach (ChecklistTemplate::with('sections.items')->get() as $template) {
            foreach ($template->sections as $section) {
                foreach ($section->items as $item) {
                    $itemMap[$template->type_code][$item->item_code] = $item->id;
                }
            }
        }

        if (empty($itemMap)) {
            $this->error('No checklist templates found. Run: php artisan db:seed --class=ChecklistImportSeeder');
            return self::FAILURE;
        }

        $districts = District::pluck('id', 'name');
        $fallbackInspector = User::role('Administrator')->first()
            ?? User::first();

        if (! $fallbackInspector) {
            $this->error('No users exist. Run the UserSeeder first.');
            return self::FAILURE;
        }

        $typeFilter = $this->option('type');

        $stats = ['entities' => 0, 'inspections' => 0, 'answers' => 0, 'team' => 0, 'skipped' => 0];

        $rows = $legacy->select('
            SELECT i.id AS inspection_id, i.entity_id, i.inspection_date, i.inspector_name,
                   i.status, i.observations, i.recommendations, i.owner_recommendations,
                   i.owner_rep_name, i.created_at,
                   e.entity_type_id, e.name, e.upi, e.owner, e.telephone, e.email,
                   e.use_type, e.zoning, e.district, e.sector, e.cell,
                   e.latitude, e.longitude
            FROM inspections i
            JOIN entities e ON e.id = i.entity_id
            WHERE i.deleted_at IS NULL AND e.deleted_at IS NULL
            ORDER BY e.id, i.inspection_date, i.id
        ');

        $this->info(sprintf('Found %d inspection(s) in the legacy database.', count($rows)));
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        $visitCounters = [];

        foreach ($rows as $r) {
            $typeCode = self::TYPE_MAP[$r->entity_type_id] ?? null;

            if (! $typeCode || ($typeFilter && $typeCode !== $typeFilter)) {
                $stats['skipped']++;
                $bar->advance();
                continue;
            }

            $template = ChecklistTemplate::current($typeCode);
            if (! $template) {
                $stats['skipped']++;
                $bar->advance();
                continue;
            }

            if ($dry) {
                $stats['inspections']++;
                $bar->advance();
                continue;
            }

            DB::transaction(function () use (
                $r, $typeCode, $template, $itemMap, $districts,
                $fallbackInspector, $legacy, &$stats, &$visitCounters
            ) {
                // ---- Premises ----
                $entity = Entity::where('legacy_id', $r->entity_id)->first();

                $attributes = [
                    'type_code'   => $typeCode,
                    'name'        => $r->name,
                    'upi'         => $r->upi ?: null,
                    'owner'       => $r->owner ?: null,
                    'telephone'   => $r->telephone ?: null,
                    'email'       => $r->email ?: null,
                    'use_type'    => $r->use_type ?: null,
                    'zoning'      => $r->zoning ?: null,
                    'district'    => $r->district ?: null,
                    'district_id' => $districts[$r->district] ?? null,
                    'sector'      => $r->sector ?: null,
                    'cell'        => $r->cell ?: null,
                    'latitude'    => $r->latitude ?: null,
                    'longitude'   => $r->longitude ?: null,
                    'legacy_id'   => $r->entity_id,
                ];

                if (! $entity) {
                    // Deduplicate against premises already migrated.
                    $entity = Entity::findOrCreateFrom($attributes);
                    $entity->legacy_id = $r->entity_id;
                    $entity->save();
                    $stats['entities']++;
                } else {
                    $entity->fill($attributes)->save();
                }

                // ---- Inspection ----
                $key = $entity->id;
                $visitCounters[$key] = ($visitCounters[$key] ?? 0) + 1;

                $inspector = User::where('name', $r->inspector_name)->first() ?? $fallbackInspector;

                $inspection = Inspection::updateOrCreate(
                    ['legacy_id' => $r->inspection_id],
                    [
                        'entity_id'             => $entity->id,
                        'template_id'           => $template->id,
                        'type_code'             => $typeCode,
                        'inspection_date'       => $r->inspection_date,
                        'visit_type'            => $visitCounters[$key] > 1 ? 'followup' : 'new',
                        'visit_number'          => $visitCounters[$key],
                        'status'                => $r->status === 'draft' ? 'draft' : 'completed',
                        'inspector_id'          => $inspector->id,
                        'inspector_name'        => $r->inspector_name ?: $inspector->name,
                        'district_id'           => $districts[$r->district] ?? null,
                        'observations'          => $r->observations ?: null,
                        'recommendations'       => $r->recommendations ?: null,
                        'owner_recommendations' => $r->owner_recommendations ?: null,
                        'owner_rep_name'        => $r->owner_rep_name ?: null,
                    ]
                );

                if ($inspection->wasRecentlyCreated) {
                    $stats['inspections']++;
                }

                // ---- Answers, matched by item code ----
                $answers = $legacy->select('
                    SELECT ci.item_code, ia.status, ia.comment
                    FROM inspection_answers ia
                    JOIN checklist_items ci ON ci.id = ia.item_id
                    WHERE ia.inspection_id = ?', [$r->inspection_id]);

                foreach ($answers as $a) {
                    $itemId = $itemMap[$typeCode][$a->item_code] ?? null;
                    if (! $itemId) {
                        continue;   // item no longer in the published template
                    }

                    InspectionAnswer::updateOrCreate(
                        ['inspection_id' => $inspection->id, 'item_id' => $itemId],
                        [
                            'status'  => in_array($a->status, ['yes', 'no', 'na'], true) ? $a->status : null,
                            'comment' => $a->comment ?: null,
                        ]
                    );
                    $stats['answers']++;
                }

                // ---- Team ----
                $team = $legacy->select(
                    'SELECT name, institution FROM inspection_team WHERE inspection_id = ?',
                    [$r->inspection_id]
                );

                foreach ($team as $m) {
                    if (! $m->name) {
                        continue;
                    }
                    InspectionTeamMember::firstOrCreate([
                        'inspection_id' => $inspection->id,
                        'name'          => $m->name,
                    ], ['institution' => $m->institution ?: null]);
                    $stats['team']++;
                }

                $inspection->recalculate();
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Premises created', 'Inspections', 'Answers', 'Team members', 'Skipped'],
            [[$stats['entities'], $stats['inspections'], $stats['answers'], $stats['team'], $stats['skipped']]]
        );

        if ($dry) {
            $this->warn('Dry run complete — nothing was written.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Verification');
        $this->table(
            ['Premises', 'Inspections', 'Answers', 'Repeat visits'],
            [[
                Entity::count(),
                Inspection::count(),
                InspectionAnswer::count(),
                Inspection::where('visit_number', '>', 1)->count(),
            ]]
        );

        return self::SUCCESS;
    }
}