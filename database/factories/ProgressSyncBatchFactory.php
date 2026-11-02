<?php

namespace Database\Factories;

use App\Models\LearningProfile;
use App\Models\ProgressSyncBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgressSyncBatch>
 */
class ProgressSyncBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_uuid' => fake()->uuid(),
            'learning_profile_id' => LearningProfile::factory(),
            'payload_hash' => hash('sha256', fake()->uuid()),
            'result_json' => null,
            'processed_at' => now(),
        ];
    }
}
