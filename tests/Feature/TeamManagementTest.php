<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyInvitationNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = $this->companyUser('owner');
    }

    public function test_owner_sees_team_page_with_members(): void
    {
        $manager = $this->companyUser('manager');

        $this->actingAs($this->owner)->get(route('team.index'))
            ->assertOk()
            ->assertSee($this->owner->email)
            ->assertSee($manager->email);
    }

    public function test_non_owners_cannot_manage_the_team(): void
    {
        foreach (['manager', 'operator', 'viewer'] as $role) {
            $user = $this->companyUser($role);

            $this->actingAs($user)->get(route('team.index'))->assertForbidden();
            $this->actingAs($user)->post(route('team.invite'), ['email' => 'x@example.test', 'role' => 'viewer'])->assertForbidden();
        }
    }

    public function test_owner_can_invite_and_an_email_is_sent(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)
            ->post(route('team.invite'), ['email' => 'New.Person@Example.test', 'role' => 'operator'])
            ->assertSessionHasNoErrors();

        $invitation = CompanyInvitation::sole();
        $this->assertSame('new.person@example.test', $invitation->email);
        $this->assertSame('operator', $invitation->role);
        $this->assertSame($this->company->id, $invitation->company_id);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            CompanyInvitationNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new.person@example.test'
        );
    }

    public function test_owner_role_cannot_be_handed_out_by_invitation(): void
    {
        $this->actingAs($this->owner)
            ->post(route('team.invite'), ['email' => 'boss@example.test', 'role' => 'owner'])
            ->assertSessionHasErrors('role');

        $this->assertSame(0, CompanyInvitation::count());
    }

    public function test_existing_members_cannot_be_invited_again(): void
    {
        $member = $this->companyUser('viewer');

        $this->actingAs($this->owner)
            ->post(route('team.invite'), ['email' => $member->email, 'role' => 'manager'])
            ->assertSessionHasErrors('email');
    }

    public function test_owner_can_change_a_members_role(): void
    {
        $member = $this->companyUser('viewer');

        $this->actingAs($this->owner)
            ->patch(route('team.members.update', $member), ['role' => 'manager'])
            ->assertSessionHasNoErrors();

        $this->assertSame('manager', $this->company->roleOf($member));
    }

    public function test_members_cannot_be_promoted_to_owner(): void
    {
        $member = $this->companyUser('manager');

        $this->actingAs($this->owner)
            ->patch(route('team.members.update', $member), ['role' => 'owner'])
            ->assertSessionHasErrors('role');

        $this->assertSame('manager', $this->company->roleOf($member));
    }

    public function test_owner_cannot_change_their_own_role(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('team.members.update', $this->owner), ['role' => 'viewer'])
            ->assertForbidden();

        $this->assertSame('owner', $this->company->roleOf($this->owner));
    }

    public function test_another_owner_cannot_be_demoted_or_removed(): void
    {
        $coOwner = $this->companyUser('owner');

        $this->actingAs($this->owner)->patch(route('team.members.update', $coOwner), ['role' => 'viewer'])->assertForbidden();
        $this->actingAs($this->owner)->delete(route('team.members.remove', $coOwner))->assertForbidden();

        $this->assertSame('owner', $this->company->roleOf($coOwner));
    }

    public function test_owner_can_remove_a_member(): void
    {
        $member = $this->companyUser('operator');

        $this->actingAs($this->owner)->delete(route('team.members.remove', $member))->assertRedirect();

        $this->assertFalse($member->belongsToCompany($this->company));
        $this->assertNull($member->fresh()->current_company_id);
        $this->assertNull($this->company->roleOf($member));
    }

    public function test_cannot_manage_users_from_another_company(): void
    {
        $otherCompany = Company::factory()->create();
        $outsider = $this->companyUser('viewer', $otherCompany);

        $this->actingAs($this->owner)->patch(route('team.members.update', $outsider), ['role' => 'manager'])->assertNotFound();
        $this->actingAs($this->owner)->delete(route('team.members.remove', $outsider))->assertNotFound();

        $this->assertSame('viewer', $otherCompany->roleOf($outsider));
    }

    public function test_cannot_revoke_another_companys_invitation(): void
    {
        $otherCompany = Company::factory()->create();
        $otherOwner = $this->companyUser('owner', $otherCompany);
        [$foreignInvitation] = CompanyInvitation::issue('someone@example.test', 'viewer', $otherOwner);

        $this->actingAs($this->owner)
            ->delete(route('team.invitations.revoke', $foreignInvitation))
            ->assertNotFound();

        $this->assertDatabaseHas('company_invitations', ['id' => $foreignInvitation->id]);
    }
}
