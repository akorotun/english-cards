<?php

namespace App;

use App\Models\Topic;
use Illuminate\Http\Request;

class VocabularyImportPreview
{
    public function __construct(
        private VocabularyImportAccess $access,
        private VocabularyCsvParser $parser,
    ) {}

    /** @return array<string, mixed> */
    public function inspect(Request $request, string $path): array
    {
        $ownerId = $this->access->authorize($request);
        $scope = $request->input('scope', 'private');
        $parsed = $this->parser->parse($path);
        $levels = array_values(array_unique(array_column($parsed['topics'], 'level')));
        $candidates = Topic::query()->where('user_id', $ownerId)
            ->whereIn('level', $levels)->get()->groupBy('level');
        $existingLinks = 0;
        $newTopics = 0;
        $reusedTopics = 0;
        $ambiguities = [];

        foreach ($parsed['topics'] as $group) {
            $matches = ($candidates->get($group['level']) ?? collect())
                ->filter(fn (Topic $topic): bool => TextKeyNormalizer::key($topic->name) === $group['name_key']);

            if ($matches->count() > 1) {
                $ambiguities[] = [
                    'row' => $group['row'],
                    'level' => $group['level'],
                    'topic' => $group['name'],
                    'message' => 'Є кілька тем із такою назвою та рівнем. Уточніть назви тем перед імпортом.',
                ];

                continue;
            }

            if ($matches->isEmpty()) {
                $newTopics++;

                continue;
            }

            $reusedTopics++;
            $topic = $matches->first();
            $attachedPairs = $topic->words()
                ->get(['words.word_en_key', 'words.word_uk_key'])
                ->mapWithKeys(fn ($word): array => [
                    json_encode([$word->word_en_key, $word->word_uk_key], JSON_THROW_ON_ERROR) => true,
                ]);

            foreach ($group['pairs'] as $pairId => $pair) {
                if ($attachedPairs->has($pairId)) {
                    $existingLinks++;
                }
            }
        }

        $uniqueLinks = array_sum(array_map(
            fn (array $group): int => count($group['pairs']),
            $parsed['topics'],
        ));

        return [
            'scope' => $scope,
            'rows' => $parsed['rows'],
            'topics' => count($parsed['topics']),
            'unique_pairs' => $parsed['unique_pairs'],
            'unique_links' => $uniqueLinks,
            'duplicate_rows' => $parsed['duplicate_rows'],
            'new_topics' => $newTopics,
            'reused_topics' => $reusedTopics,
            'existing_links' => $existingLinks,
            'potential_new_links' => $ambiguities === [] ? $uniqueLinks - $existingLinks : null,
            'ambiguities' => $ambiguities,
            'warnings' => $parsed['warnings'],
            'can_import' => $ambiguities === [],
        ];
    }
}
