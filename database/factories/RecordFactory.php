<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Record;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Record>
 */
class RecordFactory extends Factory
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
            'import_batch_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'company' => fake()->company(),
            'city' => fake()->city(),
            'country' => fake()->country(),
        ];
    }
}
