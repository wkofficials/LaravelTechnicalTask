<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ImportBatch;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_dashboard_works_for_every_role_and_shows_only_what_it_may(): void
    {
        foreach (['owner', 'manager', 'operator', 'viewer'] as $role) {
            $user = $this->companyUser($role);
            ImportBatch::factory()->create(['user_id' => $user->id, 'original_filename' => "{$role}-file.csv"]);

            $response = $this->actingAs($user)->get(route('dashboard'));
            $response->assertOk();

            in_array($role, ['owner', 'manager'], true)
                ? $response->assertSee('Monthly usage')
                : $response->assertDontSee('Monthly usage');

            $role === 'viewer'
                ? $response->assertDontSee('Recent imports')
                : $response->assertSee("{$role}-file.csv");
        }
    }

    public function test_owner_can_update_company_settings(): void
    {
        $owner = $this->companyUser('owner');

        $this->actingAs($owner)->put(route('company.settings.update'), [
            'name' => 'Renamed Co',
            'timezone' => 'Asia/Karachi',
            'email_import_notifications' => '0',
        ])->assertSessionHasNoErrors();

        $this->company->refresh();
        $this->assertSame(['Renamed Co', 'Asia/Karachi', false], [$this->company->name, $this->company->timezone, $this->company->email_import_notifications]);
        $this->assertSame(['name', 'timezone', 'email_import_notifications'], ActivityLog::where('action', 'company.settings_updated')->sole()->metadata['fields']);
    }

    public function test_settings_cannot_change_plan_or_status(): void
    {
        $owner = $this->companyUser('owner');

        $this->actingAs($owner)->put(route('company.settings.update'), [
            'name' => 'Co', 'timezone' => 'UTC', 'status' => 'suspended', 'plan_id' => 999,
        ]);

        $this->company->refresh();
        $this->assertTrue($this->company->isActive());
        $this->assertNull($this->company->plan_id);
    }

    public function test_invalid_timezone_is_rejected_and_managers_cannot_change_settings(): void
    {
        $owner = $this->companyUser('owner');

        $this->actingAs($owner)->put(route('company.settings.update'), ['name' => 'Co', 'timezone' => 'Mars/Base'])
            ->assertSessionHasErrors('timezone');

        $this->actingAs($this->companyUser('manager'))->get(route('company.settings'))->assertForbidden();
    }
}
