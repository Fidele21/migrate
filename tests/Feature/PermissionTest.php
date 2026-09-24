<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\User;
use Database\Seeders\DistrictSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DistrictSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWith(string $role, ?string $districtCode = null): User
    {
        $user = User::factory()->create([
            'district_id' => $districtCode
                ? District::where('code', $districtCode)->value('id')
                : null,
            'is_active' => true,
        ]);
        $user->assignRole($role);
        return $user->fresh();
    }

    /** Only the Chief Inspector may give final approval. */
    public function test_only_chief_inspector_can_approve_documents(): void
    {
        $this->assertTrue($this->userWith('Chief Inspector')->can('document.approve'));

        foreach ([
            ['Senior Inspector', null],
            ['Director of Inspection', 'GASABO'],
            ['Inspector', 'GASABO'],
            ['Administrator', null],
        ] as [$role, $district]) {
            $this->assertFalse(
                $this->userWith($role, $district)->can('document.approve'),
                "$role must not hold document.approve"
            );
        }
    }

    /** The Administrator manages the system but never signs. */
    public function test_administrator_cannot_sign_or_verify(): void
    {
        $admin = $this->userWith('Administrator');

        $this->assertFalse($admin->can('document.approve'));
        $this->assertFalse($admin->can('document.verify.city'));
        $this->assertFalse($admin->can('document.verify.district'));
        $this->assertFalse($admin->can('document.sign.inspector'));
        $this->assertTrue($admin->can('user.manage'));
    }

    /** A Director is confined to their own district. */
    public function test_director_is_scoped_to_own_district(): void
    {
        $gasabo   = District::where('code', 'GASABO')->value('id');
        $kicukiro = District::where('code', 'KICUKIRO')->value('id');

        $director = $this->userWith('Director of Inspection', 'GASABO');

        $this->assertTrue($director->coversDistrict($gasabo));
        $this->assertFalse(
            $director->coversDistrict($kicukiro),
            'A Gasabo Director must not reach Kicukiro work'
        );
    }

    /** Chief and Senior Inspectors operate across all districts. */
    public function test_city_wide_roles_cover_every_district(): void
    {
        $ids = District::pluck('id');

        foreach (['Chief Inspector', 'Senior Inspector'] as $role) {
            $user = $this->userWith($role);
            $this->assertTrue($user->isCityWide());
            foreach ($ids as $id) {
                $this->assertTrue($user->coversDistrict($id), "$role should cover district $id");
            }
        }
    }

    /** A deactivated account loses all district access. */
    public function test_inactive_user_covers_nothing(): void
    {
        $user = $this->userWith('Chief Inspector');
        $user->update(['is_active' => false]);

        $this->assertFalse($user->fresh()->coversDistrict(District::first()->id));
    }

    /** An Inspector drafts and signs their own report, nothing more. */
    public function test_inspector_can_draft_but_not_verify(): void
    {
        $inspector = $this->userWith('Inspector', 'NYARUGENGE');

        $this->assertTrue($inspector->can('document.draft'));
        $this->assertTrue($inspector->can('document.sign.inspector'));
        $this->assertTrue($inspector->can('inspection.create'));

        $this->assertFalse($inspector->can('document.verify.district'));
        $this->assertFalse($inspector->can('document.verify.city'));
        $this->assertFalse($inspector->can('document.approve'));
        $this->assertFalse($inspector->can('inspection.view.all'));
    }
}
