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
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CheckProgressSyncTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshIsolatedDatabase;
    }

    public function refreshDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('english_cards_test', config('database.connections.mysql.database'));
        $this->assertSame('english_cards_test', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->refreshIsolatedDatabase();
    }

    public function test_sync_requires_authentication_verification_owner_and_csrf(): void
    {
        $this->postJson(route('check.sync'), [])->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())->postJson(route('check.sync'), [])->assertForbidden();
        $profile = LearningProfile::factory()->create();
        $word = Word::factory()->create();
        $payload = $this->payload($profile, [$word->id]);
        $other = User::factory()->create();
        $this->actingAs($other)->postJson(route('check.sync'), $payload)->assertForbidden();
        $payload['user_id'] = $other->id;
        $this->postJson(route('check.sync'), $payload)->assertNotFound();
        $this->actingAs($profile->user);
        $this->app->instance('env', 'local');
        $this->postJson(route('check.sync'), $this->payload($profile, [$word->id]))->assertStatus(419);
        $this->assertDatabaseCount('progress_sync_batches', 0);
    }

    public function test_first_known_answer_increments_missing_and_all_existing_levels_once(): void
    {
        $profile = LearningProfile::factory()->create();
        $words = Word::factory()->count(5)->create();
        Topic::factory()->hasAttached($words)->create();
        foreach ([0, 1, 2, 3] as $index => $level) {
            WordProgress::factory()->for($profile)->for($words[$index])->create(['knowledge_level' => $level]);
        }
        $this->actingAs($profile->user)->postJson(route('check.sync'), $this->payload($profile, $words->modelKeys()))
            ->assertOk()->assertJsonPath('status', 'synced')->assertJsonPath('applied', 5);
        $this->assertSame([1, 2, 3, 3, 1], WordProgress::orderBy('word_id')->pluck('knowledge_level')->all());
        $this->assertDatabaseCount('progress_sync_batches', 1);
        $this->assertNotNull(ProgressSyncBatch::first()->processed_at);
    }

    public function test_first_unknown_resets_every_level_and_both_repeat_answers_leave_zero(): void
    {
        $profile = LearningProfile::factory()->create();
        $words = Word::factory()->count(5)->create();
        Topic::factory()->hasAttached($words)->create();
        foreach ([0, 1, 2, 3] as $index => $level) {
            WordProgress::factory()->for($profile)->for($words[$index])->create(['knowledge_level' => $level]);
        }
        $payload = $this->payload($profile, $words->modelKeys());
        $payload['answers'] = $words->map(fn (Word $word, int $index): array => [
            'word_id' => $word->id, 'first' => 'dont_know', 'repeat' => $index % 2 === 0 ? 'know' : 'dont_know',
        ])->all();
        $this->actingAs($profile->user)->postJson(route('check.sync'), $payload)->assertOk();
        $this->assertSame([0, 0, 0, 0, 0], WordProgress::orderBy('word_id')->pluck('knowledge_level')->all());
    }

    public function test_shared_word_is_applied_once_to_original_profile_after_active_profile_changes(): void
    {
        $profile = LearningProfile::factory()->create();
        $other = LearningProfile::factory()->for($profile->user)->create();
        $word = Word::factory()->create();
        $topics = Topic::factory()->hasAttached($word)->count(2)->create();
        $this->actingAs($profile->user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id]);
        $selection = $this->get(route('check.start', ['topic_ids' => $topics->modelKeys()]))->assertOk();
        $this->assertCount(1, $selection->viewData('words'));
        $this->withSession([ActiveLearningProfile::SESSION_KEY => $other->id])
            ->postJson(route('check.sync'), $this->payload($profile, [$word->id]))->assertOk();
        $this->assertDatabaseHas('word_progress', ['learning_profile_id' => $profile->id, 'word_id' => $word->id, 'knowledge_level' => 1]);
        $this->assertDatabaseMissing('word_progress', ['learning_profile_id' => $other->id]);
        $this->postJson(route('check.sync'), $this->payload($other, [$word->id]))->assertOk();
        $this->assertSame([1, 1], WordProgress::orderBy('learning_profile_id')->pluck('knowledge_level')->all());
    }

    public function test_interrupted_first_and_second_rounds_apply_only_rated_words_and_allow_no_answers(): void
    {
        $profile = LearningProfile::factory()->create();
        $words = Word::factory()->count(3)->create();
        Topic::factory()->hasAttached($words)->create();
        WordProgress::factory()->for($profile)->for($words[2])->create(['knowledge_level' => 2]);
        $payload = $this->payload($profile, $words->modelKeys());
        $payload['status'] = 'interrupted';
        $payload['answers'] = [
            ['word_id' => $words[0]->id, 'first' => 'know'],
            ['word_id' => $words[1]->id, 'first' => 'dont_know'],
        ];
        $this->actingAs($profile->user)->postJson(route('check.sync'), $payload)->assertOk()->assertJsonPath('applied', 2);
        $this->assertSame([1, 0, 2], WordProgress::orderBy('word_id')->pluck('knowledge_level')->all());
        $payload['session_uuid'] = (string) Str::uuid();
        $payload['answers'] = [
            ['word_id' => $words[0]->id, 'first' => 'dont_know', 'repeat' => 'know'],
            ['word_id' => $words[1]->id, 'first' => 'dont_know'],
            ['word_id' => $words[2]->id, 'first' => 'know'],
        ];
        $this->postJson(route('check.sync'), $payload)->assertOk();
        $this->assertSame([0, 0, 3], WordProgress::orderBy('word_id')->pluck('knowledge_level')->all());
        $payload['session_uuid'] = (string) Str::uuid();
        $payload['answers'] = [];
        $this->postJson(route('check.sync'), $payload)->assertOk()->assertJsonPath('applied', 0);
        $this->assertSame([0, 0, 3], WordProgress::orderBy('word_id')->pluck('knowledge_level')->all());
    }

    public function test_replay_returns_identical_result_even_after_word_disappears_and_changed_payload_conflicts(): void
    {
        $profile = LearningProfile::factory()->create();
        $word = Word::factory()->create();
        Topic::factory()->hasAttached($word)->create();
        $payload = $this->payload($profile, [$word->id]);
        $this->actingAs($profile->user);
        $first = $this->postJson(route('check.sync'), $payload)->assertOk()->json();
        $this->postJson(route('check.sync'), array_reverse($payload, true))->assertOk()->assertExactJson($first);
        $this->assertSame(1, WordProgress::first()->knowledge_level);
        $changed = [...$payload, 'status' => 'interrupted'];
        $this->postJson(route('check.sync'), $changed)->assertConflict();
        $changed = $payload;
        $changed['answers'][0] = ['word_id' => $word->id, 'first' => 'dont_know', 'repeat' => 'know'];
        $this->postJson(route('check.sync'), $changed)->assertConflict();
        $other = LearningProfile::factory()->for($profile->user)->create();
        $this->postJson(route('check.sync'), [...$payload, 'learning_profile_id' => $other->id])->assertConflict();
        $word->delete();
        $this->postJson(route('check.sync'), $payload)->assertOk()->assertExactJson($first);
        $this->assertDatabaseCount('progress_sync_batches', 1);
    }

    public function test_missing_and_inaccessible_words_are_skipped_without_disclosing_their_content(): void
    {
        $profile = LearningProfile::factory()->create();
        $words = Word::factory()->count(4)->create();
        Topic::factory()->for($profile->user)->hasAttached($words[0])->create();
        Topic::factory()->for(User::factory())->hasAttached($words[1])->create();
        $words[2]->delete();
        $this->actingAs($profile->user)->postJson(route('check.sync'), $this->payload($profile, $words->modelKeys()))
            ->assertOk()->assertJsonPath('applied', 1)->assertJsonPath('skipped_word_ids', $words->slice(1)->values()->modelKeys())
            ->assertDontSee($words[1]->word_en);
        $this->assertDatabaseCount('word_progress', 1);
    }

    #[DataProvider('invalidBatches')]
    public function test_invalid_batch_is_rejected_atomically(string $mutation): void
    {
        $profile = LearningProfile::factory()->create();
        $word = Word::factory()->create();
        Topic::factory()->hasAttached($word)->create();
        $payload = $this->payload($profile, [$word->id]);
        switch ($mutation) {
            case 'uuid': $payload['session_uuid'] = 'wrong';
                break;
            case 'duplicate-words': $payload['word_ids'][] = $word->id;
                break;
            case 'duplicate-answers': $payload['answers'][] = $payload['answers'][0];
                break;
            case 'outside-set': $payload['answers'][0]['word_id'] = $word->id + 1;
                break;
            case 'extra-answer': $payload['status'] = 'interrupted';
                $payload['answers'][] = ['word_id' => $word->id + 1, 'first' => 'know'];
                break;
            case 'third-round': $payload['answers'][0]['round'] = 3;
                break;
            case 'invalid-first': $payload['answers'][0]['first'] = 'yes';
                break;
            case 'repeat-known': $payload['answers'][0]['repeat'] = 'know';
                break;
            case 'invalid-repeat': $payload['answers'][0] = ['word_id' => $word->id, 'first' => 'dont_know', 'repeat' => 'yes'];
                break;
            case 'missing-repeat': $payload['answers'][0]['first'] = 'dont_know';
                break;
            case 'missing-answer': $payload['answers'] = [];
                break;
            case 'repeat-before-first-end': $payload['word_ids'][] = $word->id + 1;
                $payload['status'] = 'interrupted';
                $payload['answers'][0] = ['word_id' => $word->id, 'first' => 'dont_know', 'repeat' => 'know'];
                break;
            case 'order': $payload['word_ids'] = [$word->id + 1, $word->id];
                $payload['status'] = 'interrupted';
                break;
            case 'level': $payload['knowledge_level'] = 3;
                break;
            case 'answer-level': $payload['answers'][0]['knowledge_level'] = 3;
                break;
            case 'status': $payload['status'] = 'in_progress';
                break;
            case 'too-many': $payload['word_ids'] = range(1, 10001);
                break;
            case 'not-list': $payload['word_ids'] = ['card' => $word->id];
                break;
            case 'null-repeat': $payload['answers'][0]['repeat'] = null;
                break;
        }
        $this->actingAs($profile->user)->postJson(route('check.sync'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('word_progress', 0);
        $this->assertDatabaseCount('progress_sync_batches', 0);
    }

    /** @return array<string, array{string}> */
    public static function invalidBatches(): array
    {
        $cases = ['uuid', 'duplicate-words', 'duplicate-answers', 'outside-set', 'extra-answer', 'third-round', 'invalid-first', 'repeat-known', 'invalid-repeat', 'missing-repeat', 'missing-answer', 'repeat-before-first-end', 'order', 'level', 'answer-level', 'status', 'too-many', 'not-list', 'null-repeat'];

        return array_combine($cases, array_map(fn (string $case): array => [$case], $cases));
    }

    public function test_oversized_request_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('check.sync'), ['padding' => str_repeat('x', 1024 * 1024)])->assertStatus(413);
        $this->assertDatabaseCount('progress_sync_batches', 0);
    }

    public function test_failure_after_progress_write_rolls_back_progress_and_batch_then_retry_succeeds(): void
    {
        $profile = LearningProfile::factory()->create();
        $word = Word::factory()->create();
        Topic::factory()->hasAttached($word)->create();
        WordProgress::factory()->for($profile)->for($word)->create(['knowledge_level' => 1]);
        $payload = $this->payload($profile, [$word->id]);
        $fail = true;
        ProgressSyncBatch::updating(static function () use (&$fail): void {
            if ($fail) {
                throw new RuntimeException('Simulated batch result write failure');
            }
        });
        try {
            $this->actingAs($profile->user)->postJson(route('check.sync'), $payload)->assertStatus(500);
            $this->assertSame(1, WordProgress::first()->knowledge_level);
            $this->assertDatabaseCount('progress_sync_batches', 0);
            $fail = false;
            $this->postJson(route('check.sync'), $payload)->assertOk();
            $this->assertSame(2, WordProgress::first()->knowledge_level);
        } finally {
            ProgressSyncBatch::flushEventListeners();
        }
    }

    /** @param list<int> $ids
     * @return array<string, mixed>
     */
    private function payload(LearningProfile $profile, array $ids): array
    {
        return [
            'session_uuid' => (string) Str::uuid(), 'user_id' => $profile->user_id,
            'learning_profile_id' => $profile->id, 'status' => 'completed', 'word_ids' => $ids,
            'answers' => array_map(fn (int $id): array => ['word_id' => $id, 'first' => 'know'], $ids),
        ];
    }
}
