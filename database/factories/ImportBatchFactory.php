<?php

namespace Database\Factories;

use App\Enums\DuplicatePolicy;
use App\Enums\ImportBatchStatus;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => fn () => app(CurrentCompany::class)->id() ?? Company::factory(),
            'user_id' => User::factory(),
            'dataset_key' => 'contacts',
            // A confirmed mapping for files laid out as name,email,phone,company,city,country.
            'headers' => ['name', 'email', 'phone', 'company', 'city', 'country'],
            'mapping' => ['name' => 0, 'email' => 1, 'phone' => 2, 'company' => 3, 'city' => 4, 'country' => 5],
            'duplicate_policy' => DuplicatePolicy::Reject,
            'original_filename' => fake()->word().'.xlsx',
            'file_path' => null,
            'status' => ImportBatchStatus::Pending,
            'total_rows' => null,
            'processed_rows' => 0,
            'failed_rows' => 0,
        ];
    }

    /**
     * Uploaded and analyzed, waiting for the user to confirm the mapping.
     */
    public function awaitingConfirmation(array $headers = ['Full Name', 'E-mail', 'Mobile'], array $previewRows = []): static
    {
        return $this->state(fn () => [
            'status' => ImportBatchStatus::AwaitingConfirmation,
            'headers' => $headers,
            'preview_rows' => $previewRows,
            'mapping' => null,
            'duplicate_policy' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => ImportBatchStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportBatchStatus::Completed,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);
    }
}
