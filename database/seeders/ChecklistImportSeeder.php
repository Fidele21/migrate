<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Models\ChecklistTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Imports the existing checklists into versioned native templates.
 *
 * The 19 sections and 91 items built with the inspection unit are the
 * most valuable asset in the project. Bringing them across as a seeder
 * means that if a database is ever wiped again, `php artisan db:seed`
 * restores them in seconds rather than by hand from a dump.
 */
class ChecklistImportSeeder extends Seeder
{
    private const TYPE_MAP = [
        1 => ['code' => 'building', 'name' => 'Occupied Building Fire & Security Inspection'],
        2 => ['code' => 'petrol',   'name' => 'Petrol Station Inspection'],
    ];

    public function run(): void
    {
        $legacy = DB::connection('legacy');

        try {
            $legacy->getPdo();
        } catch (\Throwable $e) {
            /* Reported and skipped rather than thrown, so a server without
               the old database still seeds everything else. */
            $this->command->warn('Legacy database unreachable — checklist import skipped.');
            $this->command->line('  ' . $e->getMessage());
            $this->command->line('  Set LEGACY_DB_* in .env, then: php artisan db:seed --class=ChecklistImportSeeder --force');

            return;
        }

        foreach (self::TYPE_MAP as $legacyTypeId => $meta) {

            if (ChecklistTemplate::where('type_code', $meta['code'])->exists()) {
                $this->command->warn("Template for {$meta['code']} already exists — skipped.");
                continue;
            }

            /* One transaction per template: a read that fails midway would
               otherwise leave a template with no sections behind, and the
               check above would then skip it for good on the next run. */
            DB::transaction(function () use ($legacy, $legacyTypeId, $meta) {
                $this->importType($legacy, $legacyTypeId, $meta);
            });
        }
    }

    private function importType($legacy, int $legacyTypeId, array $meta): void
    {
        $template = ChecklistTemplate::create([
            'type_code'      => $meta['code'],
            'version'        => 1,
            'name'           => $meta['name'],
            'effective_from' => now()->toDateString(),
            'published_at'   => now(),
        ]);

        $sections = $legacy->select(
            'SELECT id, section_number, title, sort_order
             FROM checklist_sections WHERE entity_type_id = ? ORDER BY sort_order',
            [$legacyTypeId]
        );

        $items = 0;

        foreach ($sections as $s) {
            $section = ChecklistSection::create([
                'template_id'    => $template->id,
                'section_number' => $s->section_number,
                'title'          => $s->title,
                'sort_order'     => $s->sort_order ?? 0,
            ]);

            $rows = $legacy->select(
                'SELECT item_code, label, max_score, sort_order
                 FROM checklist_items WHERE section_id = ? ORDER BY sort_order',
                [$s->id]
            );

            foreach ($rows as $r) {
                ChecklistItem::create([
                    'section_id' => $section->id,
                    'item_code'  => $r->item_code,
                    'label'      => $r->label,
                    'max_score'  => $r->max_score ?? 0,
                    'sort_order' => $r->sort_order ?? 0,
                ]);
                $items++;
            }
        }

        $total = ChecklistItem::whereIn('section_id', $template->sections()->pluck('id'))->sum('max_score');

        $this->command->info(sprintf(
            '%s: %d sections, %d items, %s points total.',
            $meta['name'], count($sections), $items, rtrim(rtrim(number_format((float) $total, 2), '0'), '.')
        ));
    }
}
