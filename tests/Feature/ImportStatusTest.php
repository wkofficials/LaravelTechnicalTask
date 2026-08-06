<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $batch = ImportBatch::factory()->create();

        $this->get(route('imports.status', $batch))->assertRedirect(route('login'));
    }

    public function test_users_without_import_permission_are_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $batch = ImportBatch::factory()->create(['user_id' => $viewer->id]);

        $this->actingAs($viewer)->get(route('imports.status', $batch))->assertForbidden();
    }

    public function test_owner_can_view_their_own_batch_status(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $batch = ImportBatch::factory()->create([
            'user_id' => $manager->id,
            'status' => ImportBatchStatus::Processing,
            'total_rows' => 200,
            'processed_rows' => 50,
            'failed_rows' => 2,
        ]);

        $response = $this->actingAs($manager)->getJson(route('imports.status', $batch));

        $response->assertOk();
        $response->assertJson([
            'id' => $batch->id,
            'status' => 'processing',
            'is_finished' => false,
            'total_rows' => 200,
            'processed_rows' => 50,
            'failed_rows' => 2,
            'percentage' => 25,
        ]);
    }

    public function test_a_different_user_cannot_view_someone_elses_batch_status(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('manager');

        $otherManager = User::factory()->create();
        $otherManager->assignRole('manager');

        $batch = ImportBatch::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherManager)
            ->getJson(route('imports.status', $batch))
            ->assertForbidden();
    }

    public function test_admin_can_view_any_batch_status(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('manager');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $batch = ImportBatch::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($admin)
            ->getJson(route('imports.status', $batch))
            ->assertOk();
    }

    public function test_percentage_is_null_when_total_rows_is_unknown(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $batch = ImportBatch::factory()->create([
            'user_id' => $manager->id,
            'status' => ImportBatchStatus::Processing,
            'total_rows' => null,
            'processed_rows' => 40,
        ]);

        $response = $this->actingAs($manager)->getJson(route('imports.status', $batch));

        $response->assertOk();
        $response->assertJsonPath('percentage', null);
    }

    public function test_completed_batch_reports_finished_true(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $batch = ImportBatch::factory()->completed()->create([
            'user_id' => $manager->id,
            'total_rows' => 10,
            'processed_rows' => 10,
        ]);

        $response = $this->actingAs($manager)->getJson(route('imports.status', $batch));

        $response->assertOk();
        $response->assertJsonPath('is_finished', true);
        $response->assertJsonPath('percentage', 100);
    }
}
