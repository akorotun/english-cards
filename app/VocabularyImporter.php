<?php

namespace App;

use App\Models\Topic;
use App\Models\Word;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class VocabularyImporter
{
    public function __construct(
        private VocabularyImportAccess $access,
        private VocabularyImportUpload $upload,
        private VocabularyCsvParser $parser,
    ) {}

    /** @return array<string, int|string> */
    public function confirm(Request $request, string $token): array
    {
        $ownerId = $this->access->authorize($request);
        $parsed = $this->parser->parse($this->upload->resolve($request, $token));
        $locks = $this->lockNames($ownerId, $parsed['topics']);
        $acquired = [];

        try {
            foreach ($locks as $lock) {
                if ((int) (DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lock])->acquired ?? 0) !== 1) {
                    throw new RuntimeException('Не вдалося заблокувати теми для імпорту. Спробуйте ще раз.');
                }

                $acquired[] = $lock;
            }

            $this->upload->resolve($request, $token);

            $report = DB::transaction(function () use ($request, $ownerId, $parsed): array {
                $this->access->authorize($request);
                $candidates = Topic::query()->where('user_id', $ownerId)
                    ->whereIn('level', array_values(array_unique(array_column($parsed['topics'], 'level'))))
                    ->get()->groupBy('level');
                $matchedTopics = [];

                foreach ($parsed['topics'] as $groupId => $group) {
                    $matches = ($candidates->get($group['level']) ?? collect())
                        ->filter(fn (Topic $topic): bool => TextKeyNormalizer::key($topic->name) === $group['name_key']);

                    if ($matches->count() > 1) {
                        throw ValidationException::withMessages([
                            'file' => "Рядок {$group['row']}: Є кілька тем із назвою «{$group['name']}» та рівнем {$group['level']}. Уточніть назви перед імпортом.",
                        ]);
                    }

                    $matchedTopics[$groupId] = $matches->first();
                }

                $newTopics = 0;
                $reusedTopics = 0;
                $newWords = 0;
                $reusedWords = 0;
                $newLinks = 0;
                $reusedLinks = 0;
                $topics = [];

                foreach ($matchedTopics as $groupId => $topic) {
                    if ($topic === null) {
                        $group = $parsed['topics'][$groupId];
                        $topic = Topic::create(['user_id' => $ownerId, 'level' => $group['level'], 'name' => $group['name']]);
                        $newTopics++;
                    } else {
                        $reusedTopics++;
                    }

                    $topics[$groupId] = $topic;
                }

                $pairs = [];

                foreach ($parsed['topics'] as $group) {
                    foreach ($group['pairs'] as $pairId => $pair) {
                        $pairs[$pairId] = $pair;
                    }
                }

                ksort($pairs);
                $words = [];

                foreach ($pairs as $pairId => $pair) {
                    $word = Word::query()->where('word_en_key', $pair['word_en_key'])
                        ->where('word_uk_key', $pair['word_uk_key'])->first();

                    if ($word === null) {
                        try {
                            $word = Word::create(['word_en' => $pair['word_en'], 'word_uk' => $pair['word_uk']]);
                            $newWords++;
                        } catch (UniqueConstraintViolationException $exception) {
                            $word = Word::query()->where('word_en_key', $pair['word_en_key'])
                                ->where('word_uk_key', $pair['word_uk_key'])->lockForUpdate()->first();

                            if ($word === null) {
                                throw $exception;
                            }

                            $reusedWords++;
                        }
                    } else {
                        $reusedWords++;
                    }

                    $words[$pairId] = $word;
                }

                foreach ($parsed['topics'] as $groupId => $group) {
                    $topic = $topics[$groupId];
                    $existingWordIds = array_fill_keys($topic->words()->pluck('words.id')->all(), true);

                    foreach ($group['pairs'] as $pairId => $pair) {
                        $wordId = $words[$pairId]->id;

                        if (isset($existingWordIds[$wordId])) {
                            $reusedLinks++;

                            continue;
                        }

                        try {
                            $topic->words()->attach($wordId);
                            $newLinks++;
                        } catch (UniqueConstraintViolationException $exception) {
                            if (! $topic->words()->whereKey($wordId)->exists()) {
                                throw $exception;
                            }

                            $reusedLinks++;
                        }
                    }
                }

                return [
                    'scope' => $request->input('scope', 'private'),
                    'rows' => $parsed['rows'],
                    'topics' => count($parsed['topics']),
                    'unique_pairs' => $parsed['unique_pairs'],
                    'unique_links' => $newLinks + $reusedLinks,
                    'duplicate_rows' => $parsed['duplicate_rows'],
                    'new_topics' => $newTopics,
                    'reused_topics' => $reusedTopics,
                    'new_words' => $newWords,
                    'reused_words' => $reusedWords,
                    'new_links' => $newLinks,
                    'reused_links' => $reusedLinks,
                ];
            }, 3);

            $this->upload->forget($request, $token);

            return $report;
        } finally {
            foreach (array_reverse($acquired) as $lock) {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $topics
     * @return list<string>
     */
    private function lockNames(?int $ownerId, array $topics): array
    {
        $locks = [];

        foreach ($topics as $group) {
            $identity = json_encode([$ownerId, $group['level'], $group['name_key']], JSON_THROW_ON_ERROR);
            $locks['vti:'.substr(hash('sha256', $identity), 0, 60)] = true;
        }

        $names = array_keys($locks);
        sort($names);

        return $names;
    }
}
