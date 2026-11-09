<?php

namespace App;

use App\Models\LearningProfile;
use App\Models\ProgressSyncBatch;
use App\Models\Word;
use App\Models\WordProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CheckProgressSync
{
    public const MAX_WORDS = 10000;

    /**
     * @param  array{session_uuid: string, user_id: int, learning_profile_id: int, status: string, word_ids: list<int>, answers: list<array{word_id: int, first: string, repeat?: string}>}  $payload
     * @return array{session_uuid: string, learning_profile_id: int, status: string, applied: int, skipped_word_ids: list<int>, processed_at: string}
     */
    public function apply(LearningProfile $profile, array $payload): array
    {
        $hash = hash('sha256', json_encode([
            'session_uuid' => $payload['session_uuid'], 'user_id' => $payload['user_id'],
            'learning_profile_id' => $payload['learning_profile_id'], 'status' => $payload['status'],
            'word_ids' => $payload['word_ids'], 'answers' => $payload['answers'],
        ], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($profile, $payload, $hash): array {
                LearningProfile::query()->where('user_id', $payload['user_id'])->lockForUpdate()->findOrFail($profile->id);
                $existing = ProgressSyncBatch::query()->find($payload['session_uuid']);

                if ($existing !== null) {
                    return $this->previousResult($existing, $profile->id, $hash);
                }

                $processedAt = now()->startOfSecond();
                $batch = ProgressSyncBatch::create([
                    'session_uuid' => $payload['session_uuid'], 'learning_profile_id' => $profile->id,
                    'payload_hash' => $hash, 'processed_at' => $processedAt,
                ]);
                $answerIds = array_column($payload['answers'], 'word_id');
                $accessibleIds = Word::query()->whereIn('id', $answerIds)
                    ->whereHas('topics', function (Builder $query) use ($payload): void {
                        $query->where(function (Builder $query) use ($payload): void {
                            $query->whereNull('topics.user_id')->orWhere('topics.user_id', $payload['user_id']);
                        });
                    })->orderBy('id')->lockForUpdate()->pluck('id')->all();
                $levels = WordProgress::query()->where('learning_profile_id', $profile->id)
                    ->whereIn('word_id', $accessibleIds)->pluck('knowledge_level', 'word_id');
                $allowed = array_fill_keys($accessibleIds, true);
                $rows = [];
                $skipped = [];

                foreach ($payload['answers'] as $answer) {
                    $wordId = $answer['word_id'];

                    if (! isset($allowed[$wordId])) {
                        $skipped[] = $wordId;

                        continue;
                    }

                    $rows[] = [
                        'learning_profile_id' => $profile->id, 'word_id' => $wordId,
                        'knowledge_level' => $answer['first'] === 'know' ? min(3, ($levels[$wordId] ?? 0) + 1) : 0,
                        'created_at' => $processedAt, 'updated_at' => $processedAt,
                    ];
                }

                foreach (array_chunk($rows, 1000) as $chunk) {
                    WordProgress::query()->upsert($chunk, ['learning_profile_id', 'word_id'], ['knowledge_level', 'updated_at']);
                }

                $result = [
                    'session_uuid' => $payload['session_uuid'], 'learning_profile_id' => $profile->id,
                    'status' => 'synced', 'applied' => count($rows), 'skipped_word_ids' => $skipped,
                    'processed_at' => $processedAt->toISOString(),
                ];
                $batch->update(['result_json' => $result]);

                return $result;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = ProgressSyncBatch::query()->find($payload['session_uuid']);

            if ($existing === null) {
                throw $exception;
            }

            return $this->previousResult($existing, $profile->id, $hash);
        }
    }

    /** @return array{session_uuid: string, learning_profile_id: int, status: string, applied: int, skipped_word_ids: list<int>, processed_at: string} */
    private function previousResult(ProgressSyncBatch $batch, int $profileId, string $hash): array
    {
        abort_unless($batch->learning_profile_id === $profileId && hash_equals($batch->payload_hash, $hash), 409, 'UUID уже використано для іншого пакета.');

        return $batch->result_json;
    }
}
