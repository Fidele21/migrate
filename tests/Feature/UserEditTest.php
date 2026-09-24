<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\User;
use Database\Seeders\DistrictSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An administrator can correct an account's details, within the same
 * role-and-district rules that govern creating one.
 */
class UserEditTest extends TestCase
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
            'district_id' => $districtCode ? District::where('code', $districtCode)->value('id') : null,
            'is_active'   => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name'        => 'MUKAMANA Claire',
            'email'       => 'claire@kigalicity.gov.rw',
            'role'        => 'Inspector',
            'district_id' => District::where('code', 'GASABO')->value('id'),
        ];
    }

    public function test_an_administrator_can_change_email_district_and_role(): void
    {
        $admin  = $this->userWith('Administrator');
        $target = $this->userWith('Inspector', 'GASABO');

        $this->actingAs($admin)
            ->put(route('users.update', $target), $this->payload([
                'email'       => 'moved@kigalicity.gov.rw',
                'role'        => 'Director of Inspection',
                'district_id' => District::where('code', 'KICUKIRO')->value('id'),
            ]))
            ->assertRedirect(route('users.index'));

        $target->refresh();

        $this->assertSame('moved@kigalicity.gov.rw', $target->email);
        $this->assertSame(District::where('code', 'KICUKIRO')->value('id'), $target->district_id);
        $this->assertTrue($target->hasRole('Director of Inspection'));
        // The role it held before has to go with the change.
        $this->assertFalse($target->hasRole('Inspector'));
    }

    /** Only an Administrator holds user.manage. */
    public function test_a_chief_inspector_cannot_edit_accounts(): void
    {
        $target = $this->userWith('Inspector', 'GASABO');

        $this->actingAs($this->userWith('Chief Inspector'))
            ->put(route('users.update', $target), $this->payload())
            ->assertForbidden();
    }

    public function test_a_city_wide_role_loses_its_district(): void
    {
        $target = $this->userWith('Inspector', 'GASABO');

        $this->actingAs($this->userWith('Administrator'))
            ->put(route('users.update', $target), $this->payload([
                'role'        => 'Chief Inspector',
                'district_id' => District::where('code', 'GASABO')->value('id'),
            ]));

        $this->assertNull($target->fresh()->district_id);
    }

    public function test_a_district_bound_role_must_have_a_district(): void
    {
        $target = $this->userWith('Senior Inspector');

        $this->actingAs($this->userWith('Administrator'))
            ->put(route('users.update', $target), $this->payload([
                'role'        => 'Inspector',
                'district_id' => null,
            ]))
            ->assertSessionHasErrors('district_id');
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        $taken  = $this->userWith('Inspector', 'GASABO');
        $target = $this->userWith('Inspector', 'GASABO');

        $this->actingAs($this->userWith('Administrator'))
            ->put(route('users.update', $target), $this->payload(['email' => $taken->email]))
            ->assertSessionHasErrors('email');
    }

    /** Keeping its own address must not trip the unique rule. */
    public function test_an_account_can_keep_its_own_email(): void
    {
        $admin  = $this->userWith('Administrator');
        $target = $this->userWith('Inspector', 'GASABO');

        $this->actingAs($admin)
            ->put(route('users.update', $target), $this->payload([
                'email' => $target->email,
                'name'  => 'Corrected Name',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Corrected Name', $target->fresh()->name);
    }

    /** Otherwise an administrator could lock everyone out of administration. */
    public function test_an_administrator_cannot_change_their_own_role(): void
    {
        $admin = $this->userWith('Administrator');

        $this->actingAs($admin)
            ->put(route('users.update', $admin), $this->payload([
                'email' => $admin->email,
                'role'  => 'Inspector',
            ]))
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->hasRole('Administrator'));
    }

    public function test_an_administrator_can_still_edit_their_own_details(): void
    {
        $admin = $this->userWith('Administrator');

        $this->actingAs($admin)
            ->put(route('users.update', $admin), $this->payload([
                'name'        => 'Renamed Admin',
                'email'       => $admin->email,
                'role'        => 'Administrator',
                'district_id' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed Admin', $admin->fresh()->name);
    }

    public function test_the_edit_screen_opens(): void
    {
        $target = $this->userWith('Inspector', 'GASABO');

        $this->actingAs($this->userWith('Administrator'))
            ->get(route('users.edit', $target))
            ->assertOk()
            ->assertSee($target->email);
    }
}
