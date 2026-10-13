<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render_for_guests(): void
    {
        $this->get('/')->assertOk()->assertSee('Create your company account');
        $this->get(route('terms'))->assertOk()->assertSee('Placeholder');
        $this->get(route('privacy'))->assertOk()->assertSee('Placeholder');
    }

    public function test_pricing_shows_public_plans_with_their_limits_and_prices(): void
    {
        Plan::where('key', 'standard')->first()->forceFill(['price_monthly_cents' => 4900, 'currency' => 'USD'])->save();
        Plan::where('key', 'business')->first()->forceFill(['is_public' => false])->save();

        $this->get(route('pricing'))
            ->assertOk()
            ->assertSee('USD 49.00')
            ->assertSee('25,000 imported rows / month')
            ->assertDontSee('Business');
    }

    public function test_sample_file_has_the_dataset_headings_and_examples(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $operator = $this->companyUser('operator');

        $response = $this->actingAs($operator)->get(route('imports.sample', 'contacts'));

        $response->assertOk()->assertDownload('contacts-sample.csv');
        $lines = explode("\n", trim(preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent())));
        $this->assertSame('Name,Email,Phone,Company,City,Country', $lines[0]);
        $this->assertCount(3, $lines);
    }

    public function test_dashboard_shows_getting_started_until_done(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = $this->companyUser('owner');

        $this->actingAs($owner)->get(route('dashboard'))->assertSee('Getting started')->assertSee('Import your first file');
    }

    public function test_reimporting_our_own_escaped_values_restores_them(): void
    {
        $field = new \App\Datasets\Field('phone', 'Phone');

        $this->assertSame('+92-300', $field->normalize("'+92-300"));
        $this->assertSame("'quoted'", $field->normalize("'quoted'"), 'ordinary apostrophes are kept');
    }
}
