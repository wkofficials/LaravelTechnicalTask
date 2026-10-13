<?php

namespace Tests\Feature;

use App\Jobs\ProcessExcelImport;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Record;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tenant A must never see or change Tenant B's data, whatever IDs it puts
 * in URLs or request bodies.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $ownerA;

    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->companyA = Company::factory()->create(['name' => 'Company A']);
        $this->companyB = Company::factory()->create(['name' => 'Company B']);

        $this->ownerA = $this->companyUser('owner', $this->companyA);
        $this->ownerB = $this->companyUser('owner', $this->companyB);
    }

    private function recordIn(Company $company, array $attributes = []): Record
    {
        return Record::factory()->create(['company_id' => $company->id] + $attributes);
    }

    private function batchIn(Company $company, User $user): ImportBatch
    {
        return ImportBatch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
    }

    public function test_record_list_only_shows_own_company_records(): void
    {
        $this->recordIn($this->companyA, ['name' => 'Alpha Person']);
        $this->recordIn($this->companyB, ['name' => 'Bravo Person']);

        $this->actingAs($this->ownerA)->get(route('records.index', 'contacts'))
            ->assertOk()
            ->assertSee('Alpha Person')
            ->assertDontSee('Bravo Person');
    }

    public function test_search_does_not_reach_other_company_records(): void
    {
        $this->recordIn($this->companyB, ['name' => 'Bravo Secret']);

        $this->actingAs($this->ownerA)->get(route('records.index', ['dataset' => 'contacts', 'q' => 'Bravo']))
            ->assertOk()
            ->assertDontSee('Bravo Secret');
    }

    public function test_cannot_open_edit_form_of_another_companys_record(): void
    {
        $recordB = $this->recordIn($this->companyB);

        $this->actingAs($this->ownerA)->get(route('records.edit', ['contacts', $recordB]))->assertNotFound();
    }

    public function test_cannot_update_another_companys_record(): void
    {
        $recordB = $this->recordIn($this->companyB, ['name' => 'Original']);

        $this->actingAs($this->ownerA)->put(route('records.update', ['contacts', $recordB]), [
            'name' => 'Hijacked',
            'email' => 'hijack@example.test',
        ])->assertNotFound();

        $this->assertDatabaseHas('records', ['id' => $recordB->id, 'name' => 'Original']);
    }

    public function test_cannot_delete_another_companys_record(): void
    {
        $recordB = $this->recordIn($this->companyB);

        $this->actingAs($this->ownerA)->delete(route('records.destroy', ['contacts', $recordB]))->assertNotFound();

        $this->assertDatabaseHas('records', ['id' => $recordB->id]);
    }

    public function test_created_record_belongs_to_the_active_company_even_if_another_company_id_is_posted(): void
    {
        $this->actingAs($this->ownerA)->post(route('records.store', 'contacts'), [
            'name' => 'New Person',
            'email' => 'new@example.test',
            'company_id' => $this->companyB->id,
        ])->assertRedirect(route('records.index', 'contacts'));

        $this->assertDatabaseHas('records', ['email' => 'new@example.test', 'company_id' => $this->companyA->id]);
        $this->assertDatabaseMissing('records', ['email' => 'new@example.test', 'company_id' => $this->companyB->id]);
    }

    public function test_the_same_email_may_exist_in_two_companies(): void
    {
        $this->recordIn($this->companyB, ['email' => 'shared@example.test']);

        // If uniqueness weren't per company, this would fail and also reveal
        // that the email exists in Company B.
        $this->actingAs($this->ownerA)->post(route('records.store', 'contacts'), [
            'name' => 'Shared Person',
            'email' => 'shared@example.test',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Record::withoutGlobalScopes()->where('email', 'shared@example.test')->count());
    }

    public function test_cannot_view_another_companys_import_status(): void
    {
        $batchB = $this->batchIn($this->companyB, $this->ownerB);

        $this->actingAs($this->ownerA)->getJson(route('imports.status', $batchB))->assertNotFound();
    }

    public function test_uploaded_import_belongs_to_the_active_company(): void
    {
        Storage::fake('local');
        \Illuminate\Support\Facades\Queue::fake();

        $this->actingAs($this->ownerA)->postJson(route('imports.store'), [
            'dataset' => 'contacts',
            'file' => \Illuminate\Http\UploadedFile::fake()->create('contacts.csv', 1, 'text/csv'),
            'company_id' => $this->companyB->id,
        ])->assertOk();

        $this->assertSame([$this->companyA->id], ImportBatch::withoutGlobalScopes()->pluck('company_id')->all());
    }

    public function test_queued_import_writes_records_into_the_batchs_company_only(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/a.csv', "name,email\nAlice,alice@example.test\n");

        $batchA = $this->batchIn($this->companyA, $this->ownerA);
        $batchA->forceFill(['file_path' => 'imports/a.csv'])->save();

        // Simulate a worker that last handled Company B's work.
        app(CurrentCompany::class)->set($this->companyB);

        app()->call([new ProcessExcelImport($batchA), 'handle']);

        $this->assertDatabaseHas('records', ['email' => 'alice@example.test', 'company_id' => $this->companyA->id]);
        $this->assertSame(0, Record::withoutGlobalScopes()->where('company_id', $this->companyB->id)->count());
    }

    public function test_tenant_models_cannot_be_created_without_a_company_context(): void
    {
        app(CurrentCompany::class)->clear();

        $this->expectException(\LogicException::class);

        Record::create(['name' => 'Orphan', 'email' => 'orphan@example.test']);
    }

    public function test_removed_member_loses_access_on_the_next_request(): void
    {
        $manager = $this->companyUser('manager', $this->companyA);

        $this->actingAs($manager)->get(route('records.index', 'contacts'))->assertOk();

        $this->companyA->removeMember($manager);

        $this->actingAs($manager->fresh())->get(route('records.index', 'contacts'))->assertRedirect(route('companies.none'));
    }

    public function test_suspended_company_members_are_locked_out(): void
    {
        $this->companyA->update(['status' => Company::STATUS_SUSPENDED]);

        $this->actingAs($this->ownerA)->get(route('records.index', 'contacts'))->assertRedirect(route('companies.none'));
    }

    public function test_a_tampered_current_company_id_falls_back_to_a_real_membership(): void
    {
        // Pretend the stored active company was pointed at a company the user isn't in.
        $this->ownerA->forceFill(['current_company_id' => $this->companyB->id])->save();

        $this->recordIn($this->companyA, ['name' => 'Alpha Person']);
        $this->recordIn($this->companyB, ['name' => 'Bravo Person']);

        $this->actingAs($this->ownerA)->get(route('records.index', 'contacts'))
            ->assertOk()
            ->assertSee('Alpha Person')
            ->assertDontSee('Bravo Person');

        $this->assertSame($this->companyA->id, $this->ownerA->fresh()->current_company_id);
    }

    public function test_cannot_switch_to_a_company_you_are_not_a_member_of(): void
    {
        $this->actingAs($this->ownerA)
            ->post(route('companies.switch', $this->companyB))
            ->assertNotFound();

        $this->assertSame($this->companyA->id, $this->ownerA->fresh()->current_company_id);
    }

    public function test_member_of_two_companies_sees_data_of_the_active_one(): void
    {
        $this->companyB->addMember($this->ownerA, 'viewer');

        $this->recordIn($this->companyA, ['name' => 'Alpha Person']);
        $this->recordIn($this->companyB, ['name' => 'Bravo Person']);

        $this->actingAs($this->ownerA)
            ->post(route('companies.switch', $this->companyB))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->ownerA->fresh())->get(route('records.index', 'contacts'))
            ->assertOk()
            ->assertSee('Bravo Person')
            ->assertDontSee('Alpha Person');

        // Viewer in Company B: owner powers from Company A don't carry over.
        $recordB = Record::withoutGlobalScopes()->where('name', 'Bravo Person')->first();
        $this->actingAs($this->ownerA->fresh())->delete(route('records.destroy', ['contacts', $recordB]))->assertForbidden();
    }
}
