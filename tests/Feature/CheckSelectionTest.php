<?php

namespace Tests\Feature;

use App\ActiveLearningProfile;
use App\Models\LearningProfile;
use App\Models\Topic;
use App\Models\User;
use App\Models\Word;
use App\Models\WordProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckSelectionTest extends TestCase
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

    public function test_access_requires_verified_user_and_owned_active_profile(): void
    {
        foreach (['check.index', 'check.selection', 'check.start'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->unverified()->create())->get(route('check.index'))->assertRedirect(route('verification.notice'));
        $user = User::factory()->create();
        $foreign = LearningProfile::factory()->create();
        $this->actingAs($user);
        foreach ([null, $foreign->id] as $id) {
            foreach (['check.index', 'check.selection', 'check.start'] as $route) {
                $this->withSession([ActiveLearningProfile::SESSION_KEY => $id])->get(route($route))->assertRedirect(route('profiles.index'));
            }
        }
    }

    public function test_selection_uses_visible_unique_words_and_active_profile_without_writes(): void
    {
        $profile = LearningProfile::factory()->create();
        $other = LearningProfile::factory()->for($profile->user)->create();
        $words = Word::factory()->count(4)->create();
        $system = Topic::factory()->hasAttached($words->slice(0, 3))->create();
        $own = Topic::factory()->for($profile->user)->hasAttached($words->slice(2, 2))->create();
        $foreign = Topic::factory()->for(User::factory())->hasAttached(Word::factory()->create(['word_en' => 'secret']))->create(['name' => 'Secret topic']);
        WordProgress::factory()->for($profile)->for($words[0])->create(['knowledge_level' => 3]);
        WordProgress::factory()->for($profile)->for($words[1])->create(['knowledge_level' => 2]);
        WordProgress::factory()->for($other)->for($words[2])->create(['knowledge_level' => 3]);
        $before = DB::table('word_progress')->get()->toJson();
        $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id]);
        $this->get(route('home'))->assertSee(route('check.index'));
        $this->get(route('check.index'))->assertOk()->assertSee('Перевірка')->assertDontSee('Secret topic')->assertSee('data-topic-search', false);
        $single = $this->get(route('check.selection', ['topic_ids' => [$system->id]]))->assertOk();
        $this->assertSame($words->slice(0, 3)->modelKeys(), $single->viewData('words')->modelKeys());
        $params = ['topic_ids' => [$system->id, $own->id]];
        $all = $this->get(route('check.selection', $params))->assertOk()->assertSee('4 слова')->assertSee('Почати перевірку');
        $this->assertSame($words->modelKeys(), $all->viewData('words')->modelKeys());
        $review = $this->get(route('check.start', [...$params, 'needs_review' => 1]))->assertOk()->assertSee('Підбірку сформовано');
        $this->assertSame($words->slice(1)->values()->modelKeys(), $review->viewData('words')->modelKeys());
        foreach (['check.selection', 'check.start'] as $route) {
            $this->getJson(route($route, ['topic_ids' => [$system->id, $foreign->id]]))->assertUnprocessable()->assertJsonValidationErrors('topic_ids')->assertDontSee('Secret topic')->assertDontSee('secret');
        }
        $this->assertSame($before, DB::table('word_progress')->get()->toJson());
        $this->assertSame(0, DB::table('progress_sync_batches')->count());
    }

    public function test_empty_topics_and_filtered_empty_sets_cannot_start(): void
    {
        $profile = LearningProfile::factory()->create();
        $empty = Topic::factory()->create();
        $word = Word::factory()->create();
        $topic = Topic::factory()->hasAttached($word)->create();
        WordProgress::factory()->for($profile)->for($word)->create(['knowledge_level' => 3]);
        $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id]);
        $this->get(route('check.start', ['topic_ids' => [$empty->id]]))->assertOk()->assertSee('У вибраних темах поки немає слів')->assertDontSee('Почати перевірку');
        $this->get(route('check.start', ['topic_ids' => [$topic->id], 'needs_review' => 1]))->assertOk()->assertSee('За фільтром немає карток')->assertDontSee('Почати перевірку');
    }
}
