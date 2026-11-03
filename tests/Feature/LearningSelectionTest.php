<?php

namespace Tests\Feature;

use App\ActiveLearningProfile;
use App\Models\LearningProfile;
use App\Models\ProgressSyncBatch;
use App\Models\Topic;
use App\Models\User;
use App\Models\Word;
use App\Models\WordProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LearningSelectionTest extends TestCase
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

    #[DataProvider('learningRoutes')]
    public function test_guests_and_unverified_users_cannot_access_learning(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route($route))->assertRedirect(route('verification.notice'));
    }

    /** @return array<string, array{string}> */
    public static function learningRoutes(): array
    {
        return ['topic selection' => ['learning.index'], 'direct collection request' => ['learning.selection'], 'cards' => ['learning.cards']];
    }

    #[DataProvider('invalidProfiles')]
    public function test_missing_stale_foreign_or_malformed_profile_requires_selection(?string $profileState): void
    {
        $user = User::factory()->create();
        $ownProfile = LearningProfile::factory()->for($user)->create();
        $foreignProfile = LearningProfile::factory()->create();
        $deletedProfile = LearningProfile::factory()->for($user)->create();
        $deletedId = $deletedProfile->id;
        $deletedProfile->delete();
        $topic = Topic::factory()->hasAttached(Word::factory())->create();
        $profileId = match ($profileState) {
            'foreign' => $foreignProfile->id,
            'deleted' => $deletedId,
            'string' => (string) $ownProfile->id,
            'array' => [$ownProfile->id],
            default => null,
        };

        $this->actingAs($user);

        foreach (['learning.index', 'learning.selection', 'learning.cards'] as $route) {
            $this->withSession([ActiveLearningProfile::SESSION_KEY => $profileId])
                ->get(route($route, ['topic_ids' => [$topic->id], 'learning_profile_id' => $ownProfile->id]))
                ->assertRedirect(route('profiles.index'))
                ->assertSessionHas('status', 'Для навчання виберіть доступний навчальний профіль.')
                ->assertSessionMissing(ActiveLearningProfile::SESSION_KEY);
        }
    }

    /** @return array<string, array{?string}> */
    public static function invalidProfiles(): array
    {
        return ['missing' => [null], 'foreign' => ['foreign'], 'deleted' => ['deleted'], 'string' => ['string'], 'array' => ['array']];
    }

    public function test_selection_lists_only_available_topics_with_shared_metadata_and_real_controls(): void
    {
        $profile = LearningProfile::factory()->create(['theme_preference' => 'dark']);
        $system = Topic::factory()->hasAttached(Word::factory()->count(2))->create(['name' => 'System collection']);
        $own = Topic::factory()->for($profile->user)->create(['name' => 'My collection', 'level' => 'B1']);
        Topic::factory()->for(User::factory())->hasAttached(Word::factory())->create(['name' => 'Secret collection', 'level' => 'SECRET']);

        $response = $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id])
            ->get(route('learning.index', ['user_id' => 999, 'scope' => 'foreign']));

        $response->assertOk()->assertViewIs('learning.index')->assertSee('data-theme="dark"', false)
            ->assertSee('System collection')->assertSee('My collection')->assertSee('Системна')->assertSee('Приватна')
            ->assertSee('2 слова')->assertSee('0 слів')->assertSee('B1')
            ->assertSee('name="topic_ids[]"', false)->assertSee('name="needs_review"', false)
            ->assertSee(route('learning.selection'))->assertSee('Сформувати підбірку')
            ->assertDontSee('Secret collection')->assertDontSee('SECRET')
            ->assertDontSee('Почати навчання')->assertDontSee('Пройти ще раз');
        $this->assertSame([$system->id, $own->id], $response->viewData('topics')->modelKeys());
        $this->assertSame([2, 0], $response->viewData('topics')->pluck('words_count')->all());
        $this->assertNull($response->viewData('words'));
        $this->get(route('home'))->assertOk()->assertSee(route('learning.index'));
        $this->get(route('topics.index'))->assertOk()->assertSee(route('learning.index'));
    }

    public function test_multiple_topics_deduplicate_ids_and_preserve_different_translations_without_exposing_other_words(): void
    {
        $profile = LearningProfile::factory()->create();
        $shared = Word::factory()->create(['word_en' => 'light', 'word_uk' => 'світло']);
        $different = Word::factory()->create(['word_en' => 'light', 'word_uk' => 'легкий']);
        $system = Topic::factory()->hasAttached($shared)->create(['name' => 'Same name']);
        $own = Topic::factory()->for($profile->user)->hasAttached($shared)->hasAttached($different)->create(['name' => 'Same name']);
        Topic::factory()->hasAttached(Word::factory())->create();
        Topic::factory()->for(User::factory())->hasAttached($shared)->hasAttached(Word::factory())->create();
        Word::factory()->create();

        $response = $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id])
            ->get(route('learning.selection', ['topic_ids' => [$own->id, $system->id], 'word_ids' => [999]]));

        $response->assertOk()->assertSee('Підбірку сформовано')->assertSee('Унікальних карток: <strong>2</strong>', false)
            ->assertSee('Усі картки вибраних тем')->assertSee('Почати навчання');
        $this->assertSame([$system->id, $own->id], $response->viewData('selectedTopics')->modelKeys());
        $this->assertSame([$shared->id, $different->id], $response->viewData('words')->modelKeys());
        $this->assertSame(['світло', 'легкий'], $response->viewData('words')->pluck('word_uk')->all());
        $this->assertSame(['id', 'word_en', 'word_uk'], array_keys($response->viewData('words')->first()->getAttributes()));
    }

    public function test_review_filter_uses_only_the_active_profile_and_does_not_create_or_change_progress(): void
    {
        $profile = LearningProfile::factory()->create();
        $otherProfile = LearningProfile::factory()->for($profile->user)->create();
        $foreignProfile = LearningProfile::factory()->create();
        $words = Word::factory()->count(5)->create();
        $topic = Topic::factory()->for($profile->user)->hasAttached($words)->create();

        foreach ([0, 1, 2, 3] as $level) {
            WordProgress::factory()->for($profile)->for($words[$level])->create(['knowledge_level' => $level]);
        }

        WordProgress::factory()->for($otherProfile)->for($words[4])->create(['knowledge_level' => 3]);
        WordProgress::factory()->for($foreignProfile)->for($words[0])->create(['knowledge_level' => 3]);
        ProgressSyncBatch::factory()->for($profile)->create();
        $tables = ['users', 'learning_profiles', 'topics', 'words', 'topic_word', 'word_progress', 'progress_sync_batches'];
        $before = [];

        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }

        $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id]);
        $parameters = ['topic_ids' => [$topic->id]];
        $all = $this->get(route('learning.selection', $parameters))->assertOk();
        $this->assertSame($words->modelKeys(), $all->viewData('words')->modelKeys());
        $explicitAll = $this->get(route('learning.selection', [...$parameters, 'needs_review' => 0]))->assertOk();
        $this->assertSame($words->modelKeys(), $explicitAll->viewData('words')->modelKeys());
        $reviewParameters = [...$parameters, 'needs_review' => 1, 'learning_profile_id' => $foreignProfile->id];
        $review = $this->get(route('learning.selection', $reviewParameters))->assertOk()->assertSee('рівні 0–2');
        $this->assertSame([$words[0]->id, $words[1]->id, $words[2]->id, $words[4]->id], $review->viewData('words')->modelKeys());

        $this->post(route('profiles.activate', $otherProfile))->assertRedirect(route('home'));
        $otherReview = $this->get(route('learning.selection', [...$reviewParameters, 'learning_profile_id' => $profile->id]))->assertOk();
        $this->assertSame([$words[0]->id, $words[1]->id, $words[2]->id, $words[3]->id], $otherReview->viewData('words')->modelKeys());
        $this->assertSame($otherProfile->id, $otherReview->viewData('profile')->id);
        $this->get(route('learning.index'))->assertOk();
        $this->getJson(route('learning.selection', ['topic_ids' => [999999]]))->assertUnprocessable();

        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->get()->toJson(), $table.' changed during selection');
        }

        $this->assertDatabaseMissing('word_progress', ['learning_profile_id' => $profile->id, 'word_id' => $words[4]->id]);
    }

    #[DataProvider('accountTypes')]
    public function test_foreign_and_missing_topics_are_rejected_together_without_disclosing_private_data(bool $isAdmin): void
    {
        $profile = LearningProfile::factory()->create();
        config()->set('admin.emails', $isAdmin ? $profile->user->email : '');
        $system = Topic::factory()->hasAttached(Word::factory())->create();
        $foreign = Topic::factory()->for(User::factory())->create(['name' => 'Secret collection']);
        $deleted = Topic::factory()->create();
        $deletedId = $deleted->id;
        $deleted->delete();
        $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id]);

        foreach ([$foreign->id, $deletedId, 999999] as $invalidId) {
            $response = $this->getJson(route('learning.selection', [
                'topic_ids' => [$system->id, $invalidId], 'user_id' => $foreign->user_id, 'scope' => 'system',
            ]));

            $response->assertUnprocessable()->assertJsonValidationErrors('topic_ids')
                ->assertJsonPath('errors.topic_ids.0', 'Одна або кілька тем недоступні. Оновіть вибір тем.')
                ->assertDontSee('Secret collection')->assertDontSee($foreign->user->email);
        }
    }

    /** @return array<string, array{bool}> */
    public static function accountTypes(): array
    {
        return ['ordinary account' => [false], 'admin account' => [true]];
    }

    /** @param array<string, mixed> $parameters */
    #[DataProvider('invalidParameters')]
    public function test_invalid_input_is_rejected_with_a_clear_message(array $parameters, string $error, string $message): void
    {
        $profile = LearningProfile::factory()->create();
        $topic = Topic::factory()->create();

        $response = $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id])
            ->getJson(route('learning.selection', [...['topic_ids' => [$topic->id]], ...$parameters]));

        $response->assertUnprocessable()->assertJsonValidationErrors([$error => $message]);
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function invalidParameters(): array
    {
        $topicsMessage = 'Оберіть теми зі списку доступних.';
        $idsMessage = 'Оберіть різні теми зі списку доступних.';
        $reviewMessage = 'Оберіть коректне значення фільтра «Потрібно повторити».';

        return [
            'empty topics' => [['topic_ids' => []], 'topic_ids', 'Оберіть хоча б одну доступну тему.'],
            'scalar topics' => [['topic_ids' => '1'], 'topic_ids', $topicsMessage],
            'associative topics' => [['topic_ids' => ['id' => 1]], 'topic_ids', $topicsMessage],
            'duplicate topics' => [['topic_ids' => [1, 1]], 'topic_ids.0', $idsMessage],
            'malformed topic' => [['topic_ids' => ['abc']], 'topic_ids.0', $idsMessage],
            'nested topic' => [['topic_ids' => [[1]]], 'topic_ids.0', $idsMessage],
            'zero topic' => [['topic_ids' => [0]], 'topic_ids.0', $idsMessage],
            'negative topic' => [['topic_ids' => [-1]], 'topic_ids.0', $idsMessage],
            'fractional topic' => [['topic_ids' => [1.5]], 'topic_ids.0', $idsMessage],
            'invalid filter' => [['needs_review' => 'all'], 'needs_review', $reviewMessage],
            'nested filter' => [['needs_review' => [1]], 'needs_review', $reviewMessage],
        ];
    }

    public function test_direct_collection_request_requires_topics_and_browser_errors_return_to_the_form(): void
    {
        $profile = LearningProfile::factory()->create();
        $topic = Topic::factory()->create();
        $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id]);

        $this->getJson(route('learning.selection'))->assertUnprocessable()->assertJsonValidationErrors('topic_ids');
        $this->from(route('learning.index'))->get(route('learning.selection', ['needs_review' => 1]))
            ->assertRedirect(route('learning.index'))
            ->assertSessionHasErrors(['topic_ids' => 'Оберіть хоча б одну доступну тему.']);
        $this->from(route('learning.index'))->get(route('learning.selection', ['topic_ids' => [$topic->id], 'needs_review' => 'invalid']))
            ->assertRedirect(route('learning.index'))->assertSessionHasErrors('needs_review')
            ->assertSessionHasInput('topic_ids', [(string) $topic->id]);
    }

    public function test_empty_states_distinguish_missing_topics_empty_topics_and_no_review_cards(): void
    {
        $profile = LearningProfile::factory()->create();
        Topic::factory()->for(User::factory())->create(['name' => 'Hidden empty topic']);
        $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id]);
        $this->get(route('learning.index'))->assertOk()->assertSee('Доступних тем поки немає')
            ->assertSee(route('vocabulary.import'))->assertDontSee('Сформувати підбірку')->assertDontSee('Hidden empty topic');
        $empty = Topic::factory()->create();

        $response = $this->get(route('learning.selection', ['topic_ids' => [$empty->id]]));

        $response->assertOk()->assertSee('У вибраних темах поки немає слів. Оберіть інші теми.');
        $this->assertCount(0, $response->viewData('words'));
        $word = Word::factory()->create();
        $topic = Topic::factory()->hasAttached($word)->create();
        WordProgress::factory()->for($profile)->for($word)->create(['knowledge_level' => 3]);
        $review = $this->get(route('learning.selection', ['topic_ids' => [$topic->id], 'needs_review' => 1]));
        $review->assertOk()->assertSee('За фільтром немає карток. Вимкніть «Потрібно повторити» або змініть теми.');
        $this->assertCount(0, $review->viewData('words'));
    }

    public function test_selection_escapes_topic_profile_and_level_text_and_keeps_the_selection_checked(): void
    {
        $profile = LearningProfile::factory()->create(['name' => '<b>Profile</b>']);
        $topic = Topic::factory()->create(['name' => '<script>alert("topic")</script>', 'level' => '<B>A1</B>']);

        $response = $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id])
            ->get(route('learning.selection', ['topic_ids' => [$topic->id], 'needs_review' => 1]));

        $response->assertOk()->assertSee($profile->name)->assertDontSee($profile->name, false)
            ->assertSee($topic->name)->assertDontSee($topic->name, false)
            ->assertSee($topic->level)->assertDontSee($topic->level, false);
        $this->assertMatchesRegularExpression('/value="'.$topic->id.'"\s+checked/', $response->getContent());
        $this->assertMatchesRegularExpression('/name="needs_review" value="1"\s+checked/', $response->getContent());
    }

    public function test_cards_revalidate_topics_and_profile_and_leave_progress_unchanged(): void
    {
        $profile = LearningProfile::factory()->create();
        $other = LearningProfile::factory()->for($profile->user)->create();
        $shared = Word::factory()->create(['word_en' => 'light', 'word_uk' => 'світло']);
        $different = Word::factory()->create(['word_en' => 'light', 'word_uk' => 'легкий']);
        $system = Topic::factory()->hasAttached($shared)->create();
        $own = Topic::factory()->for($profile->user)->hasAttached($shared)->hasAttached($different)->create();
        $foreign = Topic::factory()->for(User::factory())->hasAttached(Word::factory()->create(['word_en' => 'secret']))->create();
        WordProgress::factory()->for($other)->for($shared)->create(['knowledge_level' => 3]);
        $before = DB::table('word_progress')->get()->toJson();
        $parameters = ['topic_ids' => [$system->id, $own->id], 'needs_review' => 1];
        $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id]);

        $response = $this->get(route('learning.cards', [...$parameters, 'word_ids' => [$foreign->words->first()->id]]));
        $response->assertOk()->assertViewIs('learning.cards')->assertSee('Змінити теми')->assertDontSee('secret');
        $this->assertSame([$shared->id, $different->id], $response->viewData('words')->modelKeys());
        $this->withSession([ActiveLearningProfile::SESSION_KEY => $other->id]);
        $response = $this->get(route('learning.cards', $parameters));
        $this->assertSame([$different->id], $response->viewData('words')->modelKeys());
        $this->getJson(route('learning.cards', ['topic_ids' => [$system->id, $foreign->id]]))->assertUnprocessable();
        $this->getJson(route('learning.cards'))->assertUnprocessable();
        $this->assertSame($before, DB::table('word_progress')->get()->toJson());
    }

    public function test_cards_with_an_empty_collection_return_to_selection_with_an_explanation(): void
    {
        $profile = LearningProfile::factory()->create();
        $topic = Topic::factory()->create();

        $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id])
            ->get(route('learning.cards', ['topic_ids' => [$topic->id]]))
            ->assertOk()->assertViewIs('learning.index')->assertSee('У вибраних темах поки немає слів');
    }
}
