<?php

namespace Tests\Feature;

use App\CheckProgressSync;
use App\Models\LearningProfile;
use App\Models\ProgressSyncBatch;
use App\Models\Topic;
use App\Models\Word;
use App\Models\WordProgress;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Throwable;

class CheckProgressConcurrencyTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as private runIsolatedDatabaseMigrations;
    }

    public function runDatabaseMigrations(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('english_cards_test', config('database.connections.mysql.database'));
        $this->assertSame('english_cards_test', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->runIsolatedDatabaseMigrations();
    }

    public function test_concurrent_replay_applies_once_and_distinct_batches_do_not_lose_increments(): void
    {
        $profile = LearningProfile::factory()->create();
        $word = Word::factory()->create();
        Topic::factory()->hasAttached($word)->create();
        $payload = $this->payload($profile, $word);
        $results = $this->concurrently([$payload, $payload]);
        $this->assertEquals($results[0], $results[1]);
        $this->assertSame(1, WordProgress::first()->knowledge_level);
        $this->assertDatabaseCount('progress_sync_batches', 1);
        $this->concurrently([$this->payload($profile, $word), $this->payload($profile, $word)]);
        $this->assertSame(3, WordProgress::first()->knowledge_level);
        $this->assertDatabaseCount('progress_sync_batches', 3);
    }

    public function test_concurrent_same_uuid_on_two_profiles_conflicts_without_partial_progress(): void
    {
        $profile = LearningProfile::factory()->create();
        $other = LearningProfile::factory()->for($profile->user)->create();
        $word = Word::factory()->create();
        Topic::factory()->hasAttached($word)->create();
        $first = $this->payload($profile, $word);
        $second = [...$first, 'learning_profile_id' => $other->id];
        $results = $this->concurrently([$first, $second]);
        $this->assertCount(1, array_filter($results, fn (array $result): bool => ($result['status'] ?? null) === 'synced'));
        $this->assertCount(1, array_filter($results, fn (array $result): bool => ($result['error'] ?? null) === 409));
        $this->assertDatabaseCount('progress_sync_batches', 1);
        $this->assertDatabaseCount('word_progress', 1);
        $this->assertSame(1, WordProgress::first()->knowledge_level);
    }

    /** @param list<array<string, mixed>> $payloads
     * @return list<array<string, mixed>>
     */
    private function concurrently(array $payloads): array
    {
        $this->assertTrue(function_exists('pcntl_fork'));
        DB::disconnect();
        $children = [];
        foreach ($payloads as $payload) {
            [$parent, $child] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);
            if ($pid === 0) {
                fclose($parent);
                DB::purge();
                fgets($child);
                try {
                    if (DB::selectOne('SELECT DATABASE() AS name')->name !== 'english_cards_test') {
                        throw new \RuntimeException('Unsafe child database');
                    }
                    ProgressSyncBatch::creating(static function (): void {
                        usleep(150000);
                    });
                    $result = app(CheckProgressSync::class)->apply(LearningProfile::findOrFail($payload['learning_profile_id']), $payload);
                } catch (HttpException $exception) {
                    $result = ['error' => $exception->getStatusCode()];
                } catch (Throwable $exception) {
                    $result = ['exception' => $exception->getMessage()];
                }
                fwrite($child, json_encode($result, JSON_THROW_ON_ERROR)."\n");
                fclose($child);
                exit(0);
            }
            fclose($child);
            stream_set_timeout($parent, 15);
            $children[] = [$pid, $parent];
        }
        foreach ($children as [$pid, $socket]) {
            fwrite($socket, "go\n");
        }
        $results = [];
        foreach ($children as [$pid, $socket]) {
            $line = fgets($socket);
            fclose($socket);
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
            $this->assertNotFalse($line);
            $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $this->assertArrayNotHasKey('exception', $result, json_encode($result));
            $results[] = $result;
        }
        DB::purge();

        return $results;
    }

    /** @return array<string, mixed> */
    private function payload(LearningProfile $profile, Word $word): array
    {
        return [
            'session_uuid' => (string) Str::uuid(), 'user_id' => $profile->user_id,
            'learning_profile_id' => $profile->id, 'status' => 'completed', 'word_ids' => [$word->id],
            'answers' => [['word_id' => $word->id, 'first' => 'know']],
        ];
    }
}
