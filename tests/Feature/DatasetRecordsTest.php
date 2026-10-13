<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Record;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatasetRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = $this->companyUser('owner');
    }

    public function test_unknown_dataset_is_not_found(): void
    {
        $this->actingAs($this->owner)->get('/data/users/records')->assertNotFound();
        $this->actingAs($this->owner)->post('/data/users/records', ['name' => 'x'])->assertNotFound();
    }

    public function test_old_records_url_redirects_to_contacts(): void
    {
        $this->actingAs($this->owner)->get('/records')->assertRedirect('/data/contacts/records');
    }

    public function test_records_can_be_filtered_by_exact_value(): void
    {
        Record::factory()->create(['name' => 'In Lahore', 'city' => 'Lahore', 'country' => 'Pakistan']);
        Record::factory()->create(['name' => 'In Karachi', 'city' => 'Karachi', 'country' => 'Pakistan']);
        Record::factory()->create(['name' => 'In Dubai', 'city' => 'Dubai', 'country' => 'UAE']);

        $response = $this->actingAs($this->owner)->get(route('records.index', [
            'dataset' => 'contacts',
            'filter' => ['country' => 'Pakistan', 'city' => 'Lahore'],
        ]));

        $this->assertSame(['In Lahore'], $response->viewData('records')->pluck('name')->all());
    }

    public function test_unknown_filters_are_ignored(): void
    {
        Record::factory()->count(2)->create();

        $response = $this->actingAs($this->owner)->get(route('records.index', [
            'dataset' => 'contacts',
            'filter' => ['company_id' => 999, 'email' => 'x'],
        ]));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('records')->total());
    }

    public function test_filter_options_only_list_own_company_values(): void
    {
        Record::factory()->create(['country' => 'Pakistan']);
        Record::factory()->create(['country' => 'Secretland', 'company_id' => Company::factory()->create()->id]);

        $response = $this->actingAs($this->owner)->get(route('records.index', 'contacts'));

        $this->assertSame(['Pakistan'], $response->viewData('filterOptions')['country']);
        $response->assertDontSee('Secretland');
    }

    public function test_values_are_normalized_like_imports(): void
    {
        $this->actingAs($this->owner)->post(route('records.store', 'contacts'), [
            'name' => '  Jane Doe  ',
            'email' => ' Jane.Doe@Example.TEST ',
            'phone' => '   ',
        ])->assertSessionHasNoErrors();

        $record = Record::sole();
        $this->assertSame(['Jane Doe', 'jane.doe@example.test', null], [$record->name, $record->email, $record->phone]);
    }

    public function test_email_uniqueness_ignores_letter_case(): void
    {
        Record::factory()->create(['email' => 'jane@example.test']);

        $this->actingAs($this->owner)->post(route('records.store', 'contacts'), [
            'name' => 'Jane Again',
            'email' => 'JANE@example.test',
        ])->assertSessionHasErrors('email');
    }

    public function test_only_dataset_fields_are_saved(): void
    {
        $batchless = Record::factory()->create(['import_batch_id' => null]);

        $this->actingAs($this->owner)->put(route('records.update', ['contacts', $batchless]), [
            'name' => 'Renamed',
            'email' => $batchless->email,
            'import_batch_id' => 12345,
            'created_at' => '2000-01-01 00:00:00',
        ])->assertSessionHasNoErrors();

        $batchless->refresh();
        $this->assertSame('Renamed', $batchless->name);
        $this->assertNull($batchless->import_batch_id);
        $this->assertNotSame('2000-01-01', $batchless->created_at->toDateString());
    }

    public function test_validation_messages_use_field_labels(): void
    {
        $this->actingAs($this->owner)->post(route('records.store', 'contacts'), ['name' => 'X', 'email' => 'nope'])
            ->assertSessionHasErrors(['email' => 'The Email field must be a valid email address.']);
    }

    public function test_list_columns_come_from_the_dataset(): void
    {
        $this->actingAs($this->owner)->get(route('records.index', 'contacts'))
            ->assertOk()
            ->assertSeeInOrder(['Name', 'Email', 'Phone', 'Company', 'City', 'Country']);
    }
}
