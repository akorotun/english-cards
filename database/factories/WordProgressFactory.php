<?php

namespace Database\Factories;

use App\Models\LearningProfile;
use App\Models\Word;
use App\Models\WordProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WordProgress>
 */
class WordProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_profile_id' => LearningProfile::factory(),
            'word_id' => Word::factory(),
            'knowledge_level' => 0,
        ];
    }
}
