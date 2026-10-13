<?php

namespace Tests\Feature;

use App\Enums\DuplicatePolicy;
use App\Models\Company;
use App\Models\ImportTemplate;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function template(Company $company, string $name = 'Supplier list'): ImportTemplate
    {
        $template = new ImportTemplate([
            'dataset_key' => 'contacts',
            'name' => $name,
            'mapping' => ['name' => 'Full Name', 'email' => 'E-mail'],
            'duplicate_policy' => DuplicatePolicy::Reject,
            'schema_version' => 1,
        ]);
        $template->forceFill(['company_id' => $company->id])->save();

        return $template;
    }

    public function test_templates_page_lists_only_own_company_templates(): void
    {
        $operator = $this->companyUser('operator');
        $this->template($this->company, 'Ours');
        $this->template(Company::factory()->create(), 'Theirs');

        $this->actingAs($operator)->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Ours')
            ->assertDontSee('Theirs');
    }

    public function test_manager_can_rename_and_change_policy(): void
    {
        $manager = $this->companyUser('manager');
        $template = $this->template($this->company);

        $this->actingAs($manager)->patch(route('templates.update', $template), [
            'name' => 'Renamed',
            'duplicate_policy' => 'update',
        ])->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame('Renamed', $template->name);
        $this->assertSame(DuplicatePolicy::Update, $template->duplicate_policy);
    }

    public function test_operator_cannot_change_or_delete_templates(): void
    {
        $operator = $this->companyUser('operator');
        $template = $this->template($this->company);

        $this->actingAs($operator)->patch(route('templates.update', $template), ['name' => 'X', 'duplicate_policy' => 'skip'])->assertForbidden();
        $this->actingAs($operator)->delete(route('templates.destroy', $template))->assertForbidden();

        $this->assertModelExists($template);
    }

    public function test_another_companys_template_cannot_be_changed_or_deleted(): void
    {
        $owner = $this->companyUser('owner');
        $foreign = $this->template(Company::factory()->create(), 'Theirs');

        $this->actingAs($owner)->patch(route('templates.update', $foreign), ['name' => 'Hacked', 'duplicate_policy' => 'skip'])->assertNotFound();
        $this->actingAs($owner)->delete(route('templates.destroy', $foreign))->assertNotFound();

        $this->assertSame('Theirs', ImportTemplate::withoutGlobalScopes()->find($foreign->id)->name);
    }

    public function test_template_names_are_unique_per_company_only(): void
    {
        $manager = $this->companyUser('manager');
        $this->template(Company::factory()->create(), 'Shared name');
        $mine = $this->template($this->company, 'Mine');
        $this->template($this->company, 'Taken');

        $this->actingAs($manager)->patch(route('templates.update', $mine), ['name' => 'Shared name', 'duplicate_policy' => 'reject'])
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)->patch(route('templates.update', $mine), ['name' => 'Taken', 'duplicate_policy' => 'reject'])
            ->assertSessionHasErrors('name');
    }
}
