<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Record;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = $this->companyUser('owner');
    }

    public function test_record_changes_log_field_names_but_not_values(): void
    {
        $record = Record::factory()->create(['name' => 'Jane', 'phone' => '111']);

        $this->actingAs($this->owner)->put(route('records.update', ['contacts', $record]), [
            'name' => 'Jane Secret-Surname',
            'email' => $record->email,
            'phone' => '999',
        ]);

        $log = ActivityLog::where('action', 'record.updated')->sole();
        $this->assertSame($this->owner->id, $log->actor_id);
        $this->assertSame($this->company->id, $log->company_id);
        $this->assertSame(['dataset' => 'contacts', 'fields' => ['name', 'phone']], $log->metadata);
        $this->assertStringNotContainsString('Secret', json_encode($log->metadata));
        $this->assertNotNull($log->ip_address);
    }

    public function test_record_create_and_delete_are_logged(): void
    {
        $this->actingAs($this->owner)->post(route('records.store', 'contacts'), ['name' => 'A', 'email' => 'a@example.test']);
        $record = Record::sole();
        $this->actingAs($this->owner)->delete(route('records.destroy', ['contacts', $record]));

        $this->assertSame(['record.created', 'record.deleted'], ActivityLog::orderBy('id')->pluck('action')->all());
    }

    public function test_import_confirmation_and_cancellation_are_logged(): void
    {
        Bus::fake();
        $batch = ImportBatch::factory()->awaitingConfirmation(['name', 'email'])->create(['user_id' => $this->owner->id, 'total_rows' => 1]);

        $this->actingAs($this->owner)->post(route('imports.confirm', $batch), ['mapping' => ['name' => 0, 'email' => 1], 'duplicate_policy' => 'skip']);
        $this->actingAs($this->owner)->post(route('imports.cancel', $batch));

        $confirmed = ActivityLog::where('action', 'import.confirmed')->sole();
        $this->assertSame(['duplicate_policy' => 'skip', 'mapped_fields' => ['name', 'email']], $confirmed->metadata);
        $this->assertSame($batch->id, $confirmed->target_id);
        $this->assertTrue(ActivityLog::where('action', 'import.cancelled')->exists());
    }

    public function test_role_changes_are_logged_with_before_and_after(): void
    {
        $member = $this->companyUser('viewer');

        $this->actingAs($this->owner)->patch(route('team.members.update', $member), ['role' => 'manager']);

        $log = ActivityLog::where('action', 'team.role_changed')->sole();
        $this->assertSame(['from' => 'viewer', 'to' => 'manager'], $log->metadata);
        $this->assertSame($member->id, $log->target_id);
    }

    public function test_owner_sees_only_their_companys_log(): void
    {
        Audit::log('record.created', null, ['dataset' => 'contacts']);
        Audit::log('record.created', null, ['dataset' => 'from-elsewhere'], Company::factory()->create());

        $this->actingAs($this->owner)->get(route('audit.index'))
            ->assertOk()
            ->assertSee('contacts')
            ->assertDontSee('from-elsewhere');
    }

    public function test_audit_log_can_be_filtered_by_area(): void
    {
        Audit::log('record.created', null, ['dataset' => 'record-area']);
        Audit::log('team.invited', null, ['email' => 'team-area@example.test']);

        $this->actingAs($this->owner)->get(route('audit.index', ['area' => 'team']))
            ->assertSee('team-area@example.test')
            ->assertDontSee('record-area');
    }

    public function test_only_owners_can_read_the_audit_log(): void
    {
        $this->actingAs($this->companyUser('manager'))->get(route('audit.index'))->assertForbidden();
    }
}
