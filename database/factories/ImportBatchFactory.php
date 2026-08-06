<?php

namespace Database\Factories;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\User;
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
            'user_id' => User::factory(),
            'original_filename' => fake()->word().'.xlsx',
            'file_path' => null,
            'status' => ImportBatchStatus::Pending,
            'total_rows' => null,
            'processed_rows' => 0,
            'failed_rows' => 0,
        ];
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
