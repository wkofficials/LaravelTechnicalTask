<?php

namespace Tests\Feature;

use App\Models\Record;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordSearchAndSortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        return $user;
    }

    public function test_search_matches_by_name(): void
    {
        Record::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.test']);
        Record::factory()->create(['name' => 'John Smith', 'email' => 'john@example.test']);

        $response = $this->actingAs($this->actingUser())->get(route('records.index', ['q' => 'Jane']));

        $response->assertOk();
        $response->assertSee('Jane Doe');
        $response->assertDontSee('John Smith');
    }

    public function test_search_matches_by_company_across_a_different_field_than_name(): void
    {
        Record::factory()->create(['name' => 'Alice Adams', 'company' => 'Acmeworks']);
        Record::factory()->create(['name' => 'Bob Baker', 'company' => 'Globex']);

        $response = $this->actingAs($this->actingUser())->get(route('records.index', ['q' => 'Acmeworks']));

        $response->assertOk();
        $response->assertSee('Alice Adams');
        $response->assertDontSee('Bob Baker');
    }

    public function test_search_with_no_matches_returns_empty_results_without_error(): void
    {
        Record::factory()->create(['name' => 'Jane Doe']);

        $response = $this->actingAs($this->actingUser())->get(route('records.index', ['q' => 'Zzznomatch']));

        $response->assertOk();
        $response->assertSee('No records found.');
    }

    public function test_search_with_boolean_mode_special_characters_does_not_error(): void
    {
        Record::factory()->create(['name' => 'Jane Doe']);

        $response = $this->actingAs($this->actingUser())->get(route('records.index', ['q' => '+"*()<>~']));

        $response->assertOk();
    }

    public function test_sort_by_name_ascending_and_descending(): void
    {
        Record::factory()->create(['name' => 'Charlie']);
        Record::factory()->create(['name' => 'Alice']);
        Record::factory()->create(['name' => 'Bob']);

        $asc = $this->actingAs($this->actingUser())
            ->get(route('records.index', ['sort' => 'name', 'direction' => 'asc']));
        $asc->assertOk();
        $names = $asc->viewData('records')->pluck('name')->all();
        $this->assertSame(['Alice', 'Bob', 'Charlie'], $names);

        $desc = $this->actingAs($this->actingUser())
            ->get(route('records.index', ['sort' => 'name', 'direction' => 'desc']));
        $names = $desc->viewData('records')->pluck('name')->all();
        $this->assertSame(['Charlie', 'Bob', 'Alice'], $names);
    }

    public function test_invalid_sort_column_falls_back_to_default_safely(): void
    {
        Record::factory()->count(3)->create();

        $response = $this->actingAs($this->actingUser())
            ->get(route('records.index', ['sort' => 'password']));

        $response->assertOk();
    }

    public function test_pagination_preserves_search_and_sort_query_params(): void
    {
        Record::factory()->count(30)->create(['company' => 'Searchable Co']);

        $response = $this->actingAs($this->actingUser())
            ->get(route('records.index', ['q' => 'Searchable', 'sort' => 'name', 'direction' => 'asc']));

        $response->assertOk();
        $response->assertSee('sort=name', false);
        $response->assertSee('direction=asc', false);
        $response->assertSee('q=Searchable', false);
    }
}
