<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportBatchAndRecordSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_batch_casts_status_to_enum(): void
    {
        $batch = ImportBatch::factory()->create();

        $this->assertInstanceOf(ImportBatchStatus::class, $batch->status);
        $this->assertSame(ImportBatchStatus::Pending, $batch->status);
    }

    public function test_import_batch_has_many_records(): void
    {
        $batch = ImportBatch::factory()->create();
        Record::factory()->count(3)->create(['import_batch_id' => $batch->id]);

        $this->assertCount(3, $batch->fresh()->records);
    }

    public function test_record_belongs_to_import_batch(): void
    {
        $batch = ImportBatch::factory()->create();
        $record = Record::factory()->create(['import_batch_id' => $batch->id]);

        $this->assertTrue($record->importBatch->is($batch));
    }

    public function test_record_can_exist_without_a_batch_for_manually_created_records(): void
    {
        $record = Record::factory()->create(['import_batch_id' => null]);

        $this->assertNull($record->import_batch_id);
    }

    public function test_deleting_a_batch_nulls_the_records_batch_reference_instead_of_deleting_them(): void
    {
        $batch = ImportBatch::factory()->create();
        $record = Record::factory()->create(['import_batch_id' => $batch->id]);

        $batch->delete();

        $this->assertDatabaseHas('records', ['id' => $record->id, 'import_batch_id' => null]);
    }

    public function test_deleting_a_user_nulls_their_import_batches_instead_of_deleting_them(): void
    {
        $user = User::factory()->create();
        $batch = ImportBatch::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseHas('import_batches', ['id' => $batch->id, 'user_id' => null]);
    }
}
