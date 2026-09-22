<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\DistrictSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
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

    private function doc(string $status, string $districtCode, User $creator): Document
    {
        return Document::create([
            'type'        => 'letter',
            'status'      => $status,
            'district_id' => District::where('code', $districtCode)->value('id'),
            'title'       => 'Enforcement notice',
            'body_html'   => '<p>Findings</p>',
            'created_by'  => $creator->id,
        ]);
    }

    public function test_a_letter_moves_through_the_full_chain(): void
    {
        $inspector = $this->userWith('Inspector', 'GASABO');
        $director  = $this->userWith('Director of Inspection', 'GASABO');
        $senior    = $this->userWith('Senior Inspector');
        $chief     = $this->userWith('Chief Inspector');

        $d = $this->doc(Document::DRAFT, 'GASABO', $inspector);
        $this->assertTrue($inspector->can('submit', $d));

        $d->status = Document::PENDING_DIRECTOR;
        $this->assertTrue($director->can('verifyAsDirector', $d));

        $d->status = Document::PENDING_SENIOR;
        $this->assertTrue($senior->can('verifyAsSenior', $d));

        $d->status = Document::PENDING_CHIEF;
        $this->assertTrue($chief->can('approve', $d));

        $d->status = Document::APPROVED;
        $this->assertTrue($chief->can('issue', $d));
    }

    public function test_a_gasabo_director_cannot_verify_a_kicukiro_letter(): void
    {
        $inspector = $this->userWith('Inspector', 'KICUKIRO');
        $gasabo    = $this->userWith('Director of Inspection', 'GASABO');
        $kicukiro  = $this->userWith('Director of Inspection', 'KICUKIRO');

        $d = $this->doc(Document::PENDING_DIRECTOR, 'KICUKIRO', $inspector);

        $this->assertFalse($gasabo->can('verifyAsDirector', $d));
        $this->assertTrue($kicukiro->can('verifyAsDirector', $d));
    }

    public function test_stages_cannot_be_skipped(): void
    {
        $inspector = $this->userWith('Inspector', 'GASABO');
        $chief     = $this->userWith('Chief Inspector');
        $senior    = $this->userWith('Senior Inspector');

        $d = $this->doc(Document::PENDING_DIRECTOR, 'GASABO', $inspector);

        $this->assertFalse($chief->can('approve', $d), 'Chief must not approve before director and senior');
        $this->assertFalse($senior->can('verifyAsSenior', $d), 'Senior must not act before the director');
    }

    public function test_an_approved_document_cannot_be_edited(): void
    {
        $inspector = $this->userWith('Inspector', 'GASABO');
        $d = $this->doc(Document::APPROVED, 'GASABO', $inspector);

        $this->assertFalse($d->isEditable());
        $this->assertTrue($d->isFrozen());
        $this->assertFalse($inspector->can('update', $d));
    }

    public function test_a_draft_is_editable_only_by_its_author(): void
    {
        $author = $this->userWith('Inspector', 'GASABO');
        $other  = $this->userWith('Inspector', 'GASABO');

        $d = $this->doc(Document::DRAFT, 'GASABO', $author);

        $this->assertTrue($author->can('update', $d));
        $this->assertFalse($other->can('update', $d));
    }

    public function test_reviewers_can_return_a_document(): void
    {
        $inspector = $this->userWith('Inspector', 'GASABO');
        $director  = $this->userWith('Director of Inspection', 'GASABO');

        $d = $this->doc(Document::PENDING_DIRECTOR, 'GASABO', $inspector);
        $this->assertTrue($director->can('returnForRevision', $d));
    }

    public function test_a_signature_detects_later_tampering(): void
    {
        $inspector = $this->userWith('Inspector', 'GASABO');
        $chief     = $this->userWith('Chief Inspector');

        $d = $this->doc(Document::APPROVED, 'GASABO', $inspector);

        $sig = $d->signatures()->create([
            'signer_id'    => $chief->id,
            'signer_role'  => 'Chief Inspector',
            'signer_name'  => $chief->name,
            'content_hash' => $d->computeHash(),
        ]);

        $this->assertTrue($sig->fresh()->isIntact());

        $d->update(['body_html' => '<p>Altered after signing</p>']);
        $this->assertFalse($sig->fresh()->isIntact(), 'Tampering must be detectable');
    }

    public function test_nothing_can_be_hard_deleted(): void
    {
        $chief = $this->userWith('Chief Inspector');
        $d = $this->doc(Document::APPROVED, 'GASABO', $chief);

        $this->assertFalse($chief->can('delete', $d));
    }
}
