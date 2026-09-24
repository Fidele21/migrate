<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\Entity;
use App\Models\Inspection;
use App\Models\User;
use Database\Seeders\DistrictSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An inspection can be withdrawn by the people who carried it out, and
 * only while nothing has been put on the record.
 */
class InspectionDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DistrictSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWith(string $role, ?string $districtCode = 'GASABO'): User
    {
        $user = User::factory()->create([
            'district_id' => $districtCode ? District::where('code', $districtCode)->value('id') : null,
            'is_active'   => true,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function inspectionBy(User $inspector): Inspection
    {
        $entity = Entity::create(['type_code' => 'BUILDING', 'name' => 'Nyarugenge Plaza']);

        return Inspection::create([
            'entity_id'       => $entity->id,
            'type_code'       => 'BUILDING',
            'inspection_date' => now()->toDateString(),
            'inspector_id'    => $inspector->id,
            'inspector_name'  => $inspector->name,
            'district_id'     => $inspector->district_id,
        ]);
    }

    private function removeInspection(User $actor, Inspection $inspection, array $payload = [])
    {
        return $this->actingAs($actor)->delete(
            route('inspection.destroy', $inspection),
            $payload + ['reason' => 'Wrong premises', 'confirm' => 'DELETE']
        );
    }

    public function test_the_inspector_who_carried_it_out_can_delete_it(): void
    {
        $inspector  = $this->userWith('Inspector');
        $inspection = $this->inspectionBy($inspector);

        $this->removeInspection($inspector, $inspection)->assertRedirect(route('inspection.drafts'));

        $this->assertSoftDeleted('inspections', ['id' => $inspection->id]);
        $this->assertDatabaseHas('inspections', [
            'id'             => $inspection->id,
            'deleted_by'     => $inspector->id,
            'deleted_reason' => 'Wrong premises',
        ]);
    }

    public function test_a_team_member_can_delete_it(): void
    {
        $lead       = $this->userWith('Director of Inspection');
        $colleague  = $this->userWith('Inspector');
        $inspection = $this->inspectionBy($lead);
        $inspection->team()->create(['user_id' => $colleague->id, 'name' => $colleague->name]);

        $this->removeInspection($colleague, $inspection);

        $this->assertSoftDeleted('inspections', ['id' => $inspection->id]);
    }

    /** Rank is not the gate here — being on the visit is. */
    public function test_an_unrelated_inspector_cannot_delete_it(): void
    {
        $inspection = $this->inspectionBy($this->userWith('Inspector'));

        $this->removeInspection($this->userWith('Chief Inspector', null), $inspection)->assertForbidden();

        $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'deleted_at' => null]);
    }

    public function test_a_signed_report_stops_the_delete(): void
    {
        $inspector  = $this->userWith('Inspector');
        $inspection = $this->inspectionBy($inspector);

        $report = Document::create([
            'type'          => 'report',
            'status'        => Document::DRAFT,
            'district_id'   => $inspector->district_id,
            'title'         => 'Inspection report',
            'body_html'     => '<p>Findings</p>',
            'created_by'    => $inspector->id,
            'inspection_id' => $inspection->id,
        ]);

        DocumentSignature::create([
            'document_id'  => $report->id,
            'signer_id'    => $inspector->id,
            'signer_role'  => 'Inspector',
            'signer_name'  => $inspector->name,
            'content_hash' => hash('sha256', $report->body_html),
            'signed_at'    => now(),
        ]);

        $this->removeInspection($inspector, $inspection)->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'deleted_at' => null]);
    }

    public function test_an_issued_letter_stops_the_delete(): void
    {
        $inspector  = $this->userWith('Inspector');
        $inspection = $this->inspectionBy($inspector);

        Document::create([
            'type'          => 'letter',
            'status'        => Document::ISSUED,
            'district_id'   => $inspector->district_id,
            'title'         => 'Enforcement notice',
            'body_html'     => '<p>Notice</p>',
            'created_by'    => $inspector->id,
            'inspection_id' => $inspection->id,
        ]);

        $this->removeInspection($inspector, $inspection)->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'deleted_at' => null]);
    }

    /** A draft letter is not a record yet, so it goes with the visit. */
    public function test_a_draft_letter_is_soft_deleted_with_the_inspection(): void
    {
        $inspector  = $this->userWith('Inspector');
        $inspection = $this->inspectionBy($inspector);

        $draft = Document::create([
            'type'          => 'letter',
            'status'        => Document::DRAFT,
            'district_id'   => $inspector->district_id,
            'title'         => 'Enforcement notice',
            'body_html'     => '<p>Notice</p>',
            'created_by'    => $inspector->id,
            'inspection_id' => $inspection->id,
        ]);

        $this->removeInspection($inspector, $inspection);

        $this->assertSoftDeleted('documents', ['id' => $draft->id]);
    }

    public function test_the_confirmation_word_is_required(): void
    {
        $inspector  = $this->userWith('Inspector');
        $inspection = $this->inspectionBy($inspector);

        $this->removeInspection($inspector, $inspection, ['confirm' => 'delete it'])
            ->assertSessionHasErrors('confirm');

        $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'deleted_at' => null]);
    }

    public function test_a_reason_is_required(): void
    {
        $inspector  = $this->userWith('Inspector');
        $inspection = $this->inspectionBy($inspector);

        $this->removeInspection($inspector, $inspection, ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'deleted_at' => null]);
    }
}
