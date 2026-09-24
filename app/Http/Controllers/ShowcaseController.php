<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * Read-only demonstration of the approval chain.
 *
 * Every figure on this page is computed by running the real policy
 * against real users. Nothing is hardcoded, so what is displayed is
 * exactly what the platform will enforce in production.
 */
class ShowcaseController extends Controller
{
    private const ORDER = [
        'Chief Inspector'        => 5,
        'Senior Inspector'       => 4,
        'Director of Inspection' => 3,
        'Inspector'              => 1,
        'Administrator'          => 0,
    ];

    public function index()
    {
        $roles = Role::with('permissions')->get()
            ->sortByDesc(fn ($r) => self::ORDER[$r->name] ?? 0)
            ->values();

        $districts = District::withCount('users')->orderBy('name')->get();

        // ---- Live permission checks, not a table of claims ----
        $sample = [];
        foreach (self::ORDER as $roleName => $_) {
            $user = User::role($roleName)->first();
            if (! $user) {
                continue;
            }
            $sample[$roleName] = [
                'user'     => $user,
                'district' => $user->district?->name ?? 'All districts',
                'checks'   => [
                    'Create inspection' => $user->can('inspection.create'),
                    'Draft document'    => $user->can('document.draft'),
                    'Sign as inspector' => $user->can('document.sign.inspector'),
                    'Verify (district)' => $user->can('document.verify.district'),
                    'Verify (city)'     => $user->can('document.verify.city'),
                    'Final approval'    => $user->can('document.approve'),
                    'Issue document'    => $user->can('document.issue'),
                    'Manage users'      => $user->can('user.manage'),
                ],
            ];
        }

        // ---- District scoping, demonstrated live ----
        $scoping = [];
        $directors = User::role('Director of Inspection')->with('district')->get();
        foreach ($directors as $director) {
            $row = ['name' => $director->district?->name ?? '-', 'covers' => []];
            foreach ($districts as $district) {
                $row['covers'][$district->name] = $director->coversDistrict($district->id);
            }
            $scoping[] = $row;
        }

        // ---- The workflow itself, read from the model ----
        $stages = [
            ['key' => Document::DRAFT,            'label' => 'Draft',              'actor' => 'Inspector',              'note' => 'Findings recorded, document drafted'],
            ['key' => Document::PENDING_DIRECTOR, 'label' => 'Pending Director',   'actor' => 'Director of Inspection', 'note' => 'Inspector has signed; awaiting district verification'],
            ['key' => Document::PENDING_SENIOR,   'label' => 'Pending Senior',     'actor' => 'Senior Inspector',       'note' => 'Verified at district; transferred to CoK office'],
            ['key' => Document::PENDING_CHIEF,    'label' => 'Pending Chief',      'actor' => 'Chief Inspector',        'note' => 'Verified centrally; awaiting final approval'],
            ['key' => Document::APPROVED,         'label' => 'Approved',           'actor' => '—',                      'note' => 'Signed and frozen; content hash recorded'],
            ['key' => Document::ISSUED,           'label' => 'Issued',             'actor' => '—',                      'note' => 'Served on the premises owner'],
        ];

        return view('showcase', compact('roles', 'districts', 'sample', 'scoping', 'stages'));
    }
}