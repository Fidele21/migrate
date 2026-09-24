<?php

namespace Tests\Feature;

use App\Mail\AccountCredentials;
use App\Models\District;
use App\Models\User;
use Database\Seeders\DistrictSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Credentials reach their holder by email, and the account they open is
 * useless until the holder replaces the password it was opened with.
 */
class CredentialEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DistrictSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
    }

    private function administrator(): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->assignRole('Administrator');

        return $user->fresh();
    }

    public function test_creating_an_account_emails_the_temporary_password(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('users.store'), [
                'name'        => 'MUKAMANA Claire',
                'email'       => 'claire@kigalicity.gov.rw',
                'role'        => 'Inspector',
                'district_id' => District::where('code', 'GASABO')->value('id'),
            ])
            ->assertRedirect(route('users.index'));

        $created = User::where('email', 'claire@kigalicity.gov.rw')->firstOrFail();

        Mail::assertSent(AccountCredentials::class, function (AccountCredentials $mail) use ($created) {
            return $mail->hasTo('claire@kigalicity.gov.rw')
                && $mail->isReset === false
                && $mail->temporaryPassword !== ''
                // The password travels in the email, never in the database.
                && ! password_verify('', $created->password);
        });
    }

    /** The whole point of mailing it: the admin's copy must not survive. */
    public function test_a_new_account_must_change_its_password(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('users.store'), [
                'name'        => 'HABIMANA Jean',
                'email'       => 'jean@kigalicity.gov.rw',
                'role'        => 'Senior Inspector',
                'district_id' => null,
            ]);

        $this->assertTrue(
            User::where('email', 'jean@kigalicity.gov.rw')->value('must_change_password'),
            'A newly created account must be forced to set its own password.'
        );
    }

    /** The emailed password is the one that actually works. */
    public function test_the_emailed_password_signs_the_new_account_in(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('users.store'), [
                'name'        => 'UWASE Grace',
                'email'       => 'grace@kigalicity.gov.rw',
                'role'        => 'Senior Inspector',
                'district_id' => null,
            ]);

        $sent = null;
        Mail::assertSent(AccountCredentials::class, function (AccountCredentials $mail) use (&$sent) {
            $sent = $mail->temporaryPassword;

            return true;
        });

        $this->assertTrue(
            password_verify($sent, User::where('email', 'grace@kigalicity.gov.rw')->value('password')),
            'The password in the email must be the one stored on the account.'
        );
    }

    public function test_resetting_a_password_emails_the_holder(): void
    {
        $target = User::factory()->create(['email' => 'target@kigalicity.gov.rw']);
        $target->assignRole('Inspector');

        $this->actingAs($this->administrator())
            ->post(route('users.reset', $target));

        Mail::assertSent(AccountCredentials::class, fn (AccountCredentials $mail) =>
            $mail->hasTo('target@kigalicity.gov.rw') && $mail->isReset === true);

        $this->assertTrue($target->fresh()->must_change_password);
    }

    /** A mail server that is down must not cost us the reset. */
    public function test_a_failed_send_still_resets_the_password(): void
    {
        $target = User::factory()->create(['email' => 'target@kigalicity.gov.rw']);
        $target->assignRole('Inspector');
        $before = $target->password;

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP unavailable'));

        $this->actingAs($this->administrator())
            ->post(route('users.reset', $target))
            ->assertSessionHas('reset_delivered', false)
            ->assertSessionHas('reset_password');

        $this->assertNotSame($before, $target->fresh()->password);
    }
}
