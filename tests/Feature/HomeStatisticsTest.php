<?php

namespace Tests\Feature;

use App\ActiveLearningProfile;
use App\Models\LearningProfile;
use App\Models\Topic;
use App\Models\Word;
use App\Models\WordProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeStatisticsTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshIsolatedDatabase;
    }

    public function refreshDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('english_cards_test', config('database.connections.mysql.database'));
        $this->refreshIsolatedDatabase();
    }

    public function test_statistics_count_unique_accessible_words_and_only_active_profile_progress(): void
    {
        $profile = LearningProfile::factory()->create();
        $otherProfile = LearningProfile::factory()->for($profile->user)->create();
        $foreignProfile = LearningProfile::factory()->create();
        $system = Topic::factory()->create();
        $private = Topic::factory()->for($profile->user)->create();
        $foreign = Topic::factory()->for($foreignProfile->user)->create();
        $words = Word::factory()->count(7)->create();
        $system->words()->attach([$words[0]->id, $words[1]->id, $words[2]->id, $words[3]->id]);
        $private->words()->attach([$words[0]->id, $words[4]->id]);
        $foreign->words()->attach([$words[0]->id, $words[5]->id]);

        foreach ([3, 1, 2, 0] as $index => $level) {
            WordProgress::factory()->for($profile)->for($words[$index])->create(['knowledge_level' => $level]);
        }
        WordProgress::factory()->for($profile)->for($words[5])->create(['knowledge_level' => 3]);
        WordProgress::factory()->for($profile)->for($words[6])->create(['knowledge_level' => 3]);
        WordProgress::factory()->for($otherProfile)->for($words[4])->create(['knowledge_level' => 3]);
        WordProgress::factory()->for($foreignProfile)->for($words[3])->create(['knowledge_level' => 3]);

        $response = $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id])
            ->get(route('home'))->assertOk()->assertSee('Доступно слів')->assertSee('Засвоєно')->assertSee('У процесі')
            ->assertSee(route('learning.index'))->assertSee(route('check.index'));

        $statistics = $response->viewData('statistics');
        $this->assertSame(5, (int) $statistics->available);
        $this->assertSame(1, (int) $statistics->learned);
        $this->assertSame(2, (int) $statistics->in_progress);

        $otherResponse = $this->withSession([ActiveLearningProfile::SESSION_KEY => $otherProfile->id])->get(route('home'))->assertOk();
        $otherStatistics = $otherResponse->viewData('statistics');
        $this->assertSame(5, (int) $otherStatistics->available);
        $this->assertSame(1, (int) $otherStatistics->learned);
        $this->assertSame(0, (int) $otherStatistics->in_progress);
    }

    public function test_new_profile_has_zero_progress_and_empty_vocabulary_has_zero_counts(): void
    {
        $profile = LearningProfile::factory()->create();

        $empty = $this->actingAs($profile->user)->get(route('home'))->assertOk()->viewData('statistics');
        $this->assertSame([0, 0, 0], [(int) $empty->available, (int) $empty->learned, (int) $empty->in_progress]);

        Topic::factory()->hasAttached(Word::factory())->create();
        $statistics = $this->get(route('home'))->assertOk()->viewData('statistics');
        $this->assertSame([1, 0, 0], [(int) $statistics->available, (int) $statistics->learned, (int) $statistics->in_progress]);
    }
}
