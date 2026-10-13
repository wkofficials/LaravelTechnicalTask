<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = $this->companyUser('owner');
    }

    /**
     * @return array{0: CompanyInvitation, 1: string}
     */
    private function invite(string $email, string $role = 'operator'): array
    {
        app(CurrentCompany::class)->set($this->company);

        return CompanyInvitation::issue($email, $role, $this->owner);
    }

    public function test_only_the_token_hash_is_stored(): void
    {
        [$invitation, $token] = $this->invite('new@example.test');

        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
    }

    public function test_new_person_can_register_from_invitation_and_joins_with_the_invited_role(): void
    {
        [, $token] = $this->invite('new@example.test', 'operator');

        $this->get(route('invitations.show', $token))->assertOk()->assertSee($this->company->name);

        $this->post(route('invitations.register', $token), [
            'name' => 'New Person',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'new@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->belongsToCompany($this->company));
        $this->assertSame('operator', $this->company->roleOf($user));
        $this->assertSame($this->company->id, $user->current_company_id);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_invitation_can_only_be_used_once(): void
    {
        [, $token] = $this->invite('new@example.test');

        $this->post(route('invitations.register', $token), [
            'name' => 'New Person',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        auth()->logout();

        $this->get(route('invitations.show', $token))->assertNotFound();
    }

    public function test_expired_invitation_is_rejected(): void
    {
        [$invitation, $token] = $this->invite('new@example.test');
        $invitation->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->get(route('invitations.show', $token))->assertNotFound();
        $this->post(route('invitations.register', $token), [
            'name' => 'New Person',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->get(route('invitations.show', 'not-a-real-token'))->assertNotFound();
    }

    public function test_existing_user_is_sent_to_login_first(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.test']);
        [, $token] = $this->invite($existing->email);

        $this->get(route('invitations.show', $token))->assertRedirect(route('login'));
    }

    public function test_existing_user_can_accept_and_keeps_their_other_companies(): void
    {
        $otherCompany = Company::factory()->create();
        $existing = $this->companyUser('owner', $otherCompany);
        [, $token] = $this->invite($existing->email, 'viewer');

        $this->actingAs($existing)->post(route('invitations.accept', $token))->assertRedirect(route('dashboard'));

        $this->assertTrue($existing->belongsToCompany($this->company));
        $this->assertTrue($existing->belongsToCompany($otherCompany));
        $this->assertSame('viewer', $this->company->roleOf($existing));
        $this->assertSame('owner', $otherCompany->roleOf($existing));
    }

    public function test_signed_in_user_with_a_different_email_cannot_accept(): void
    {
        $someoneElse = User::factory()->create(['email' => 'someone.else@example.test']);
        [, $token] = $this->invite('intended@example.test');

        $this->actingAs($someoneElse)->post(route('invitations.accept', $token))->assertForbidden();

        $this->assertFalse($someoneElse->belongsToCompany($this->company));
    }

    public function test_invitation_to_a_suspended_company_cannot_be_used(): void
    {
        [, $token] = $this->invite('new@example.test');
        $this->company->update(['status' => Company::STATUS_SUSPENDED]);

        $this->get(route('invitations.show', $token))->assertNotFound();
    }
}
