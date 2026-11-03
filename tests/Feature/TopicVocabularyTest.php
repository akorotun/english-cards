<?php

namespace Tests\Feature;

use App\ActiveLearningProfile;
use App\Models\LearningProfile;
use App\Models\ProgressSyncBatch;
use App\Models\Topic;
use App\Models\User;
use App\Models\Word;
use App\Models\WordProgress;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TopicVocabularyTest extends TestCase
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

    public function test_guests_and_unverified_users_cannot_view_a_topic(): void
    {
        $topic = Topic::factory()->create();
        $this->get(route('topics.show', $topic))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('topics.show', $topic))->assertRedirect(route('verification.notice'));
    }

    public function test_system_topic_displays_original_words_and_translations_without_future_fields(): void
    {
        $topic = Topic::factory()->create(['name' => 'Greetings', 'level' => 'A1-A2']);
        $word = Word::factory()->create([
            'word_en' => 'Apple', 'word_uk' => 'Яблуко',
            'image_path' => 'private-image.jpg', 'example_en' => 'Example text', 'example_uk' => 'Приклад речення',
        ]);
        $topic->words()->attach($word);

        $response = $this->actingAs(User::factory()->create())->get(route('topics.show', $topic));

        $response->assertOk()->assertViewIs('topics.show')
            ->assertSee('Greetings')->assertSee('A1-A2')->assertSee('Системна')
            ->assertSee('Apple')->assertSee('Яблуко')->assertSee('Назад до тем')
            ->assertDontSee('private-image.jpg')->assertDontSee('Example text')->assertDontSee('Приклад речення')
            ->assertDontSee('Показати переклад')->assertDontSee('Не знаю');
        $this->assertSame(1, $response->viewData('topic')->words_count);
        $this->assertSame([$word->id], $response->viewData('words')->modelKeys());
        $this->assertNotSame($word->word_en_key, $response->viewData('words')->first()->word_en);
        $this->assertNotSame($word->word_uk_key, $response->viewData('words')->first()->word_uk);
        $this->assertSame(30, $response->viewData('words')->perPage());
        $response->assertDontSee('Сторінка 1 з');
    }

    public function test_owned_private_topic_is_available_with_or_without_an_active_profile(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->for($user)->hasAttached(Word::factory())->create();
        $this->actingAs($user)->get(route('topics.show', $topic))->assertOk()->assertSee('Приватна');
        $profiles = LearningProfile::factory()->count(2)->for($user)->create();
        $foreignProfile = LearningProfile::factory()->create();

        foreach ([$profiles[0]->id, $profiles[1]->id, $foreignProfile->id] as $profileId) {
            $this->withSession([ActiveLearningProfile::SESSION_KEY => $profileId])
                ->get(route('topics.show', $topic))->assertOk()->assertSee($topic->name);
        }
    }

    #[DataProvider('accountTypes')]
    public function test_foreign_private_topic_returns_404_even_for_admin_and_tampered_parameters(bool $isAdmin): void
    {
        $user = User::factory()->create();
        config()->set('admin.emails', $isAdmin ? $user->email : '');
        $foreign = User::factory()->create();
        $topic = Topic::factory()->for($foreign)->create(['name' => 'Secret topic', 'level' => 'SECRET']);
        $word = Word::factory()->create(['word_en' => 'Confidential', 'word_uk' => 'Секретний переклад']);
        $topic->words()->attach($word);
        $this->actingAs($user);

        foreach (['system', 'private'] as $scope) {
            $response = $this->get(route('topics.show', [
                'topic' => $topic->id, 'scope' => $scope, 'user_id' => $foreign->id,
                'is_admin' => true, 'learning_profile_id' => 999,
            ]));
            $response->assertNotFound()->assertDontSee('Secret topic')->assertDontSee('SECRET')
                ->assertDontSee('Confidential')->assertDontSee('Секретний переклад')->assertDontSee($foreign->email);
        }

        $this->getJson(route('topics.show', ['topic' => $topic->id, 'scope' => ['invalid']]))->assertNotFound();
    }

    /** @return array<string, array{bool}> */
    public static function accountTypes(): array
    {
        return ['ordinary account' => [false], 'admin account' => [true]];
    }

    public function test_missing_and_malformed_topic_ids_return_404(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([999999, 0, 'not-a-number'] as $topicId) {
            $this->get(route('topics.show', ['topic' => $topicId]))->assertNotFound();
        }
    }

    public function test_empty_topic_has_metadata_and_a_dedicated_empty_state(): void
    {
        $topic = Topic::factory()->create(['name' => 'Empty topic', 'level' => 'B1']);
        $response = $this->actingAs(User::factory()->create())->get(route('topics.show', $topic));

        $response->assertOk()->assertSee('Empty topic')->assertSee('B1')->assertSee('Кількість слів')
            ->assertSee('У цій темі поки немає слів')->assertDontSee('<table', false);
        $this->assertSame(0, $response->viewData('topic')->words_count);
        $this->assertTrue($response->viewData('words')->isEmpty());
    }

    public function test_words_are_only_loaded_from_the_current_topic_including_shared_words_once(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->for($user)->create();
        $other = Topic::factory()->create();
        $foreign = Topic::factory()->for(User::factory())->create();
        $shared = Word::factory()->create(['word_en' => 'friend', 'word_uk' => 'друг']);
        $otherOnly = Word::factory()->create(['word_en' => 'Elsewhere', 'word_uk' => 'Інша тема']);
        $foreignOnly = Word::factory()->create(['word_en' => 'Confidential', 'word_uk' => 'Приватний переклад']);
        Word::factory()->create(['word_en' => 'Orphan', 'word_uk' => 'Без теми']);
        $topic->words()->attach($shared);
        $other->words()->attach([$shared->id, $otherOnly->id]);
        $foreign->words()->attach([$shared->id, $foreignOnly->id]);
        $response = $this->actingAs($user)->get(route('topics.show', $topic));

        $response->assertOk()->assertSee('friend')->assertSee('друг')
            ->assertDontSee('Elsewhere')->assertDontSee('Confidential')->assertDontSee('Orphan');
        $this->assertSame([$shared->id], $response->viewData('words')->modelKeys());
        $this->assertSame(1, $response->viewData('topic')->words_count);
        $otherResponse = $this->get(route('topics.show', $other))->assertOk();
        $this->assertEqualsCanonicalizing([$shared->id, $otherOnly->id], $otherResponse->viewData('words')->modelKeys());
        $this->get(route('topics.show', $foreign))->assertNotFound();
    }

    public function test_topics_with_identical_names_have_separate_vocabularies(): void
    {
        $firstWord = Word::factory()->create(['word_en' => 'first', 'word_uk' => 'перший']);
        $secondWord = Word::factory()->create(['word_en' => 'second', 'word_uk' => 'другий']);
        $first = Topic::factory()->hasAttached($firstWord)->create(['name' => 'Same name']);
        $second = Topic::factory()->hasAttached($secondWord)->create(['name' => 'Same name']);
        $this->actingAs(User::factory()->create());

        $this->assertSame([$firstWord->id], $this->get(route('topics.show', $first))->assertOk()->viewData('words')->modelKeys());
        $this->assertSame([$secondWord->id], $this->get(route('topics.show', $second))->assertOk()->viewData('words')->modelKeys());
    }

    public function test_words_sort_by_english_then_id_with_30_per_page_and_a_stable_total(): void
    {
        $topic = Topic::factory()->create();
        $last = Word::factory()->create(['word_en' => 'Zebra', 'word_uk' => 'Зебра']);
        $sameEnglish = Word::factory()->count(31)->sequence(fn (Sequence $sequence): array => [
            'word_en' => 'Apple', 'word_uk' => 'Переклад '.$sequence->index,
        ])->create();
        $topic->words()->attach([$last->id, ...$sameEnglish->modelKeys()]);
        $this->actingAs(User::factory()->create());
        $firstPage = $this->get(route('topics.show', $topic))->assertOk();
        $secondPage = $this->get(route('topics.show', ['topic' => $topic->id, 'page' => 2]))->assertOk();

        $this->assertSame($sameEnglish->take(30)->modelKeys(), $firstPage->viewData('words')->modelKeys());
        $this->assertSame([$sameEnglish->last()->id, $last->id], $secondPage->viewData('words')->modelKeys());
        $this->assertSame(30, $firstPage->viewData('words')->perPage());
        $this->assertSame(32, $firstPage->viewData('words')->total());
        $this->assertSame(32, $firstPage->viewData('topic')->words_count);
        $this->assertSame(32, $secondPage->viewData('topic')->words_count);
        $this->get(route('topics.show', ['topic' => $topic->id, 'page' => 3]))->assertOk()
            ->assertSee('На цій сторінці немає слів')->assertSee('На початок словника');
    }

    public function test_back_link_and_word_pagination_keep_only_valid_list_context(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->for($user)->hasAttached(Word::factory()->count(31))->create(['level' => 'A1']);
        $response = $this->actingAs($user)->get(route('topics.show', [
            'topic' => $topic->id, 'scope' => 'private', 'level' => 'A1', 'topics_page' => 2,
            'user_id' => 999, 'return_url' => 'https://example.com/elsewhere',
        ]));

        $response->assertOk()->assertSee(route('topics.index', ['scope' => 'private', 'level' => 'A1', 'page' => 2]))
            ->assertDontSee('https://example.com/elsewhere')->assertDontSee('user_id=999');
        parse_str(parse_url($response->viewData('words')->nextPageUrl(), PHP_URL_QUERY), $parameters);
        $this->assertEquals(['scope' => 'private', 'level' => 'A1', 'topics_page' => '2', 'page' => '2'], $parameters);
        $nextPage = $this->get($response->viewData('words')->nextPageUrl())->assertOk();
        $this->assertSame($response->viewData('backUrl'), $nextPage->viewData('backUrl'));
        $this->assertCount(1, $nextPage->viewData('words')->items());
    }

    /** @param array<string, mixed> $parameters */
    #[DataProvider('invalidParameters')]
    public function test_invalid_context_and_word_page_parameters_are_rejected(array $parameters, string $error): void
    {
        $topic = Topic::factory()->create();
        $this->actingAs(User::factory()->create())->getJson(route('topics.show', ['topic' => $topic->id, ...$parameters]))
            ->assertUnprocessable()->assertJsonValidationErrors($error);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidParameters(): array
    {
        return [
            'invalid scope' => [['scope' => 'foreign'], 'scope'],
            'scope array' => [['scope' => ['private']], 'scope'],
            'level array' => [['level' => ['A1']], 'level'],
            'long level' => [['level' => str_repeat('A', 21)], 'level'],
            'invalid topics page' => [['topics_page' => 0], 'topics_page'],
            'topics page array' => [['topics_page' => [1]], 'topics_page'],
            'invalid word page' => [['page' => -1], 'page'],
            'word page array' => [['page' => [1]], 'page'],
        ];
    }

    public function test_topic_and_word_content_are_escaped(): void
    {
        $topic = Topic::factory()->create(['name' => '<script>alert("topic")</script>']);
        $word = Word::factory()->create(['word_en' => '<b>Apple</b>', 'word_uk' => '<em>Яблуко</em>']);
        $topic->words()->attach($word);

        $this->actingAs(User::factory()->create())->get(route('topics.show', $topic))->assertOk()
            ->assertSee($topic->name)->assertDontSee($topic->name, false)
            ->assertSee($word->word_en)->assertDontSee($word->word_en, false)
            ->assertSee($word->word_uk)->assertDontSee($word->word_uk, false);
    }

    public function test_viewing_words_leaves_the_dictionary_and_progress_unchanged(): void
    {
        $user = User::factory()->create();
        $profile = LearningProfile::factory()->for($user)->create(['theme_preference' => 'dark']);
        $knownWord = Word::factory()->create();
        $newWord = Word::factory()->create();
        $topic = Topic::factory()->for($user)->create();
        $topic->words()->attach([$knownWord->id, $newWord->id]);
        WordProgress::factory()->for($profile)->for($knownWord)->create(['knowledge_level' => 3]);
        ProgressSyncBatch::factory()->for($profile)->create();
        $tables = ['users', 'learning_profiles', 'topics', 'words', 'topic_word', 'word_progress', 'progress_sync_batches'];
        $before = [];

        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }

        $this->actingAs($user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id]);
        $response = $this->get(route('topics.show', $topic))->assertOk()->assertSee('data-theme="dark"', false);
        $this->assertEqualsCanonicalizing([$knownWord->id, $newWord->id], $response->viewData('words')->modelKeys());
        $this->get(route('topics.show', ['topic' => $topic->id, 'page' => 2, 'knowledge_level' => 0]))->assertOk();

        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->get()->toJson(), $table.' changed during viewing');
        }
    }
}
