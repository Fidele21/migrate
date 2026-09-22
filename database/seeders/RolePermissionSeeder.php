<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles and permissions for the CoK Inspection Unit.
 *
 * HIERARCHY (top to bottom):
 *   Chief Inspector          final approval and signature, all districts
 *   Senior Inspector         verification at CoK office, all districts
 *   Director of Inspection   verification and signature, one district
 *   Lead Inspector           field work, one district
 *   Inspector                field work, one district
 *
 * SUPPORTING ROLES (outside the approval chain):
 *   Secretary                letter reference, printing, stamping, scan
 *                            upload and dispatch to the premises owner
 *   Recovery Officer         records settlement of fines
 *   Administrator            accounts and system configuration only,
 *                            and can never sign or verify a document
 *
 * Senior roles retain the operational permissions of the roles below
 * them - a Chief Inspector may still record an inspection - but the
 * reverse is never true.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // ---- Inspections ----
            'inspection.create', 'inspection.followup',
            'inspection.edit.own', 'inspection.edit.district',
            'inspection.view.own', 'inspection.view.district', 'inspection.view.all',
            'inspection.archive',

            // ---- Documents: reports and letters ----
            'document.draft', 'document.edit', 'document.upload',
            'document.sign.inspector',
            'document.verify.district',
            'document.verify.city',
            'document.approve',
            'document.return',
            'document.issue',

            // ---- Letter handling (Secretary) ----
            'letter.reference', 'letter.print', 'letter.upload_scan', 'letter.dispatch',

            // ---- Fines ----
            'fine.suggest', 'fine.confirm', 'fine.payment.update', 'fine.view',

            // ---- Search, export, reference data ----
            'entity.search', 'export.excel', 'export.pdf',
            'checklist.view', 'checklist.publish',

            // ---- Administration ----
            'user.manage', 'district.manage',
            'audit.view.district', 'audit.view.all',
        ];

        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }

        // Building blocks, so the ladder stays readable.
        $field = [
            'inspection.create', 'inspection.followup', 'inspection.edit.own',
            'inspection.view.own', 'document.draft', 'document.edit',
            'document.upload', 'document.sign.inspector', 'fine.suggest',
            'fine.view', 'entity.search', 'checklist.view',
            'export.excel', 'export.pdf',
        ];

        $district = array_merge($field, [
            'inspection.edit.district', 'inspection.view.district',
            'audit.view.district',
        ]);

        $roles = [

            'Inspector' => $field,

            'Lead Inspector' => $district,

            'Director of Inspection' => array_merge($district, [
                'inspection.archive',
                'document.verify.district',
                'document.return',
            ]),

            'Senior Inspector' => array_merge($field, [
                'inspection.view.all', 'inspection.view.district',
                'document.verify.district', 'document.verify.city',
                'document.return', 'document.issue',
                'fine.confirm',
                'audit.view.all',
            ]),

            'Chief Inspector' => array_merge($field, [
                'inspection.view.all', 'inspection.view.district',
                'inspection.archive',
                'document.verify.district', 'document.verify.city',
                'document.approve', 'document.return', 'document.issue',
                'fine.confirm',
                'checklist.publish',
                'audit.view.all',
            ]),

            'Secretary' => [
                'inspection.view.all', 'entity.search', 'checklist.view',
                'letter.reference', 'letter.print', 'letter.upload_scan', 'letter.dispatch',
                'fine.suggest', 'fine.view',
                'export.excel', 'export.pdf',
            ],

            'Recovery Officer' => [
                'inspection.view.all', 'entity.search',
                'fine.view', 'fine.payment.update',
                'export.excel', 'export.pdf',
            ],

            'Administrator' => [
                'user.manage', 'district.manage',
                'inspection.view.all', 'entity.search',
                'audit.view.all', 'checklist.view', 'checklist.publish',
                'export.excel', 'export.pdf',
            ],
        ];

        foreach ($roles as $name => $perms) {
            Role::findOrCreate($name, 'web')->syncPermissions(array_unique($perms));
        }

        $this->command->info('Seeded ' . count($permissions) . ' permissions across ' . count($roles) . ' roles.');
    }
}
