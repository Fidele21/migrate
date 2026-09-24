<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\DistrictSeeder;
use Database\Seeders\FineAdjustSeeder;
use Database\Seeders\RoadPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The DEA and the two Mayors work from the Chief Inspector's reach with the
 * final approval withheld, and the Mayors alone sign off a closure.
 *
 * The narrowness of that signature is the point: it must let a Mayor
 * approve a notice that shuts a premises and nothing else whatsoever.
 */
class OversightRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DistrictSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(FineAdjustSeeder::class);
        $this->seed(RoadPermissionSeeder::class);
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

    private function letter(string $letterType, string $status = Document::PENDING_CHIEF): Document
    {
        return Document::create([
            'type'        => 'letter',
            'letter_type' => $letterType,
            'status'      => $status,
            'district_id' => District::where('code', 'GASABO')->value('id'),
            'title'       => 'Notice',
            'body_html'   => '<p>Notice</p>',
            'created_by'  => $this->userWith('Inspector', 'GASABO')->id,
        ]);
    }

    public function test_lead_inspector_no_longer_exists(): void
    {
        $this->assertNull(Role::where('name', 'Lead Inspector')->first());
    }

    public function test_the_three_oversight_roles_exist(): void
    {
        foreach (['DEA', 'Lord Mayor', 'Vice Mayor'] as $name) {
            $this->assertNotNull(Role::where('name', $name)->first(), "$name should exist");
        }
    }

    /** They match the Chief Inspector everywhere except the final approval. */
    public function test_they_have_the_chief_inspectors_reach_without_approval(): void
    {
        $chief = $this->userWith('Chief Inspector')
            ->getAllPermissions()->pluck('name')->reject(fn ($p) => $p === 'document.approve')
            ->sort()->values()->all();

        foreach (['DEA', 'Lord Mayor', 'Vice Mayor'] as $role) {
            $held = $this->userWith($role, $role === 'DEA' ? 'GASABO' : null)
                ->getAllPermissions()->pluck('name')
                ->reject(fn ($p) => $p === 'document.approve.closure')
                ->sort()->values()->all();

            $this->assertSame($chief, $held, "$role should mirror the Chief Inspector minus approval");
        }
    }

    public function test_none_of_them_hold_the_general_approval(): void
    {
        foreach ([['DEA', 'GASABO'], ['Lord Mayor', null], ['Vice Mayor', null]] as [$role, $district]) {
            $this->assertFalse(
                $this->userWith($role, $district)->can('document.approve'),
                "$role must not hold document.approve"
            );
        }
    }

    public function test_only_the_mayors_hold_the_closure_approval(): void
    {
        $this->assertTrue($this->userWith('Lord Mayor')->can('document.approve.closure'));
        $this->assertTrue($this->userWith('Vice Mayor')->can('document.approve.closure'));
        $this->assertFalse($this->userWith('DEA', 'GASABO')->can('document.approve.closure'));
    }

    /** The signature that is theirs. */
    public function test_a_mayor_may_approve_a_closure_notice(): void
    {
        foreach (['Lord Mayor', 'Vice Mayor'] as $role) {
            $mayor = $this->userWith($role);

            foreach (Document::CLOSURE_LETTERS as $type) {
                $this->assertTrue(
                    $mayor->can('approve', $this->letter($type)),
                    "$role should approve a $type notice"
                );
            }
        }
    }

    /** And nothing beyond it. */
    public function test_a_mayor_may_not_approve_any_other_letter(): void
    {
        $mayor = $this->userWith('Lord Mayor');

        foreach (['enforcement', 'fine', 'extension', 'clearance'] as $type) {
            $this->assertFalse(
                $mayor->can('approve', $this->letter($type)),
                "a Mayor must not approve a $type letter"
            );
        }
    }

    /** A report is not a closure notice, whatever else the Mayor may see. */
    public function test_a_mayor_may_not_approve_a_report(): void
    {
        $report = Document::create([
            'type'        => 'report',
            'status'      => Document::PENDING_CHIEF,
            'district_id' => District::where('code', 'GASABO')->value('id'),
            'title'       => 'Inspection report',
            'body_html'   => '<p>Findings</p>',
            'created_by'  => $this->userWith('Inspector', 'GASABO')->id,
        ]);

        $this->assertFalse($this->userWith('Lord Mayor')->can('approve', $report));
    }

    public function test_a_dea_may_not_approve_a_closure_notice(): void
    {
        $dea = $this->userWith('DEA', 'GASABO');

        foreach (Document::CLOSURE_LETTERS as $type) {
            $this->assertFalse($dea->can('approve', $this->letter($type)),
                "the DEA must not approve a $type notice");
        }
    }

    /** The exception must not have widened the Chief Inspector's own role. */
    public function test_the_chief_inspector_still_approves_everything(): void
    {
        $chief = $this->userWith('Chief Inspector');

        foreach (['enforcement', 'fine', 'closure_permanent', 'closure_temporary'] as $type) {
            $this->assertTrue($chief->can('approve', $this->letter($type)),
                "the Chief Inspector should approve a $type letter");
        }
    }

    /** Nobody without either permission gets in through the closure path. */
    public function test_an_inspector_cannot_approve_a_closure_notice(): void
    {
        $this->assertFalse(
            $this->userWith('Inspector', 'GASABO')->can('approve', $this->letter('closure_permanent'))
        );
    }

    /* ==============================================================
       The inbox — what actually distinguishes these roles
       ============================================================== */

    public function test_a_mayors_desk_shows_closure_notices_awaiting_signature(): void
    {
        $closure = $this->letter('closure_temporary');

        $this->actingAs($this->userWith('Lord Mayor'))
            ->get(route('mybox'))
            ->assertOk()
            ->assertSee($closure->title);
    }

    /** An enforcement letter at the approval step is not theirs to sign. */
    public function test_a_mayors_desk_excludes_letters_awaiting_the_chief(): void
    {
        $enforcement = $this->letter('enforcement');
        $mayor       = $this->userWith('Lord Mayor');

        $response = $this->actingAs($mayor)->get(route('mybox'))->assertOk();

        $awaiting = $response->viewData('awaiting')->pluck('id');

        $this->assertFalse(
            $awaiting->contains($enforcement->id),
            'an enforcement letter awaiting the Chief Inspector must not reach a Mayor'
        );
    }

    public function test_the_chiefs_desk_still_shows_letters_awaiting_approval(): void
    {
        $enforcement = $this->letter('enforcement');

        $awaiting = $this->actingAs($this->userWith('Chief Inspector'))
            ->get(route('mybox'))->assertOk()
            ->viewData('awaiting')->pluck('id');

        $this->assertTrue($awaiting->contains($enforcement->id));
    }

    /* ==============================================================
       Scope and account creation
       ============================================================== */

    public function test_a_dea_is_bound_to_its_district(): void
    {
        $dea = $this->userWith('DEA', 'GASABO');

        $this->assertFalse($dea->isCityWide());
        $this->assertTrue($dea->coversDistrict(District::where('code', 'GASABO')->value('id')));
        $this->assertFalse($dea->coversDistrict(District::where('code', 'KICUKIRO')->value('id')));
    }

    public function test_the_mayors_are_city_wide(): void
    {
        foreach (['Lord Mayor', 'Vice Mayor'] as $role) {
            $this->assertTrue($this->userWith($role)->isCityWide(), "$role should be city-wide");
        }
    }

    /** District scope still binds the closure signature. */
    public function test_a_dea_bound_elsewhere_cannot_reach_a_gasabo_letter(): void
    {
        $kicukiroMayor = $this->userWith('DEA', 'KICUKIRO');

        $this->assertFalse($kicukiroMayor->can('approve', $this->letter('closure_permanent')));
    }

    public function test_an_administrator_can_create_an_oversight_account(): void
    {
        $this->actingAs($this->userWith('Administrator'))
            ->post(route('users.store'), [
                'name'        => 'Lord Mayor of Kigali',
                'email'       => 'mayor@kigalicity.gov.rw',
                'role'        => 'Lord Mayor',
                'district_id' => null,
            ])
            ->assertRedirect(route('users.index'));

        $mayor = User::where('email', 'mayor@kigalicity.gov.rw')->firstOrFail();

        $this->assertTrue($mayor->hasRole('Lord Mayor'));
        $this->assertNull($mayor->district_id);
    }

    public function test_a_dea_account_must_have_a_district(): void
    {
        $this->actingAs($this->userWith('Administrator'))
            ->post(route('users.store'), [
                'name'        => 'DEA Gasabo',
                'email'       => 'dea.gasabo@kigalicity.gov.rw',
                'role'        => 'DEA',
                'district_id' => null,
            ])
            ->assertSessionHasErrors('district_id');
    }
}
