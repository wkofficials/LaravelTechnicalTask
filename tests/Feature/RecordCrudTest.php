<?php

namespace Tests\Feature;

use App\Models\Record;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return $this->companyUser($role);
    }

    public function test_guests_are_redirected_to_login_for_every_route(): void
    {
        $record = Record::factory()->create();

        $this->get(route('records.index', 'contacts'))->assertRedirect(route('login'));
        $this->get(route('records.create', 'contacts'))->assertRedirect(route('login'));
        $this->post(route('records.store', 'contacts'))->assertRedirect(route('login'));
        $this->get(route('records.edit', ['contacts', $record]))->assertRedirect(route('login'));
        $this->put(route('records.update', ['contacts', $record]))->assertRedirect(route('login'));
        $this->delete(route('records.destroy', ['contacts', $record]))->assertRedirect(route('login'));
    }

    public function test_viewer_can_only_view_the_index(): void
    {
        $viewer = $this->userWithRole('viewer');
        $record = Record::factory()->create();

        $this->actingAs($viewer)->get(route('records.index', 'contacts'))->assertOk();
        $this->actingAs($viewer)->get(route('records.create', 'contacts'))->assertForbidden();
        $this->actingAs($viewer)->post(route('records.store', 'contacts'), [])->assertForbidden();
        $this->actingAs($viewer)->get(route('records.edit', ['contacts', $record]))->assertForbidden();
        $this->actingAs($viewer)->put(route('records.update', ['contacts', $record]), [])->assertForbidden();
        $this->actingAs($viewer)->delete(route('records.destroy', ['contacts', $record]))->assertForbidden();
    }

    public function test_manager_can_view_create_and_edit_but_not_delete(): void
    {
        $manager = $this->userWithRole('manager');
        $record = Record::factory()->create();

        $this->actingAs($manager)->get(route('records.index', 'contacts'))->assertOk();
        $this->actingAs($manager)->get(route('records.create', 'contacts'))->assertOk();
        $this->actingAs($manager)->get(route('records.edit', ['contacts', $record]))->assertOk();
        $this->actingAs($manager)->delete(route('records.destroy', ['contacts', $record]))->assertForbidden();

        $this->assertDatabaseHas('records', ['id' => $record->id]);
    }

    public function test_owner_can_perform_full_crud(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->post(route('records.store', 'contacts'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'phone' => '555-0100',
            'company' => 'Acme',
            'city' => 'Springfield',
            'country' => 'USA',
        ])->assertRedirect(route('records.index', 'contacts'));

        $record = Record::where('email', 'jane@example.test')->firstOrFail();
        $this->assertNull($record->import_batch_id);

        $this->actingAs($owner)->put(route('records.update', ['contacts', $record]), [
            'name' => 'Jane Smith',
            'email' => 'jane@example.test',
            'phone' => '555-0101',
            'company' => 'Acme',
            'city' => 'Springfield',
            'country' => 'USA',
        ])->assertRedirect(route('records.index', 'contacts'));

        $this->assertDatabaseHas('records', ['id' => $record->id, 'name' => 'Jane Smith']);

        $this->actingAs($owner)->delete(route('records.destroy', ['contacts', $record]))
            ->assertRedirect(route('records.index', 'contacts'));

        $this->assertDatabaseMissing('records', ['id' => $record->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->post(route('records.store', 'contacts'), [])
            ->assertSessionHasErrors(['name', 'email']);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $manager = $this->userWithRole('manager');
        Record::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($manager)
            ->post(route('records.store', 'contacts'), [
                'name' => 'Someone',
                'email' => 'taken@example.test',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_update_allows_keeping_the_records_own_email(): void
    {
        $manager = $this->userWithRole('manager');
        $record = Record::factory()->create(['email' => 'same@example.test']);

        $this->actingAs($manager)
            ->put(route('records.update', ['contacts', $record]), [
                'name' => 'Updated Name',
                'email' => 'same@example.test',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('records', ['id' => $record->id, 'name' => 'Updated Name']);
    }

    public function test_index_paginates_results(): void
    {
        $manager = $this->userWithRole('manager');
        Record::factory()->count(30)->create();

        $response = $this->actingAs($manager)->get(route('records.index', 'contacts'));

        $response->assertOk();
        $response->assertViewHas('records', function ($records) {
            return $records->count() === 25 && $records->total() === 30;
        });
    }
}
