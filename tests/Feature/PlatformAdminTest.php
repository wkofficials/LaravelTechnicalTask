<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_super_admin' => true])->save();
    }

    public function test_platform_area_is_hidden_from_everyone_else_including_company_owners(): void
    {
        $owner = $this->companyUser('owner');

        foreach (['platform.dashboard', 'platform.companies.index', 'platform.plans'] as $route) {
            $this->actingAs($owner)->get(route($route))->assertNotFound();
        }

        $this->actingAs($owner)->put(route('platform.companies.update', $this->company), [
            'plan_id' => Plan::where('key', 'business')->value('id'),
            'status' => 'active',
        ])->assertNotFound();
    }

    public function test_admin_sees_overview_and_companies(): void
    {
        Company::factory()->create(['name' => 'Acme Traders']);

        $this->actingAs($this->admin)->get(route('platform.dashboard'))->assertOk()->assertSee('Failed jobs');
        $this->actingAs($this->admin)->get(route('platform.companies.index', ['q' => 'Acme']))->assertOk()->assertSee('Acme Traders');
    }

    public function test_admin_can_change_plan_and_suspend_and_it_is_audited_for_the_company(): void
    {
        $owner = $this->companyUser('owner');
        $business = Plan::where('key', 'business')->first();

        $this->actingAs($this->admin)->put(route('platform.companies.update', $this->company), [
            'plan_id' => $business->id,
            'status' => 'suspended',
        ])->assertRedirect();

        $this->company->refresh();
        $this->assertSame($business->id, $this->company->plan_id);
        $this->assertFalse($this->company->isActive());

        $actions = ActivityLog::where('company_id', $this->company->id)->pluck('action')->sort()->values()->all();
        $this->assertSame(['platform.company_suspended', 'platform.plan_changed'], $actions);
        $this->assertSame($this->admin->id, ActivityLog::where('action', 'platform.plan_changed')->value('actor_id'));

        // Suspension takes effect for the company's members immediately.
        $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('companies.none'));
    }

    public function test_admin_can_edit_plan_limits_within_bounds(): void
    {
        $plan = Plan::where('key', 'free')->first();
        $payload = ['name' => 'Free', 'max_users' => 5, 'max_rows_per_import' => 20000, 'max_file_size_mb' => 20, 'monthly_import_rows' => 50000, 'max_templates' => 5, 'currency' => 'USD'];

        $this->actingAs($this->admin)->put(route('platform.plans.update', $plan), $payload)->assertSessionHasNoErrors();
        $this->assertSame(5, $plan->fresh()->max_users);
        $this->assertNull(ActivityLog::where('action', 'platform.plan_limits_updated')->value('company_id'));

        $this->actingAs($this->admin)->put(route('platform.plans.update', $plan), ['max_file_size_mb' => 500] + $payload)
            ->assertSessionHasErrors('max_file_size_mb');
    }

    public function test_platform_pages_do_not_run_inside_the_admins_own_company(): void
    {
        // An admin who is also a member of a company still sees every company.
        $own = Company::factory()->create(['name' => 'Own Co']);
        $own->addMember($this->admin, 'owner');
        $this->admin->switchCompany($own);
        Company::factory()->create(['name' => 'Someone Else Ltd']);

        $this->actingAs($this->admin)->get(route('platform.companies.index'))
            ->assertSee('Own Co')
            ->assertSee('Someone Else Ltd');
    }

    public function test_artisan_command_grants_and_revokes_admin_rights(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.test']);

        $this->artisan('platform:admin', ['email' => 'ops@example.test'])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_super_admin);

        $this->artisan('platform:admin', ['email' => 'ops@example.test', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_super_admin);

        $this->artisan('platform:admin', ['email' => 'nobody@example.test'])->assertFailed();
    }

    public function test_is_super_admin_cannot_be_set_through_profile_update(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Sneaky',
            'email' => $user->email,
            'is_super_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->is_super_admin);
    }
}
