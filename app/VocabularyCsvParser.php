<?php

namespace App;

use Illuminate\Validation\ValidationException;

class VocabularyCsvParser
{
    public const MAX_BYTES = 1048576;

    public const MAX_ROWS = 2000;

    /**
     * @return array{rows: int, topics: array<string, array<string, mixed>>, unique_pairs: int, duplicate_rows: int, warnings: list<string>}
     */
    public function parse(string $path): array
    {
        $size = filesize($path);

        if ($size === false || $size === 0 || $size > self::MAX_BYTES) {
            $this->fail(1, 'Файл має бути непорожнім і не більшим за 1 МіБ.');
        }

        $content = file_get_contents($path);

        if ($content === false) {
            $this->fail(1, 'Не вдалося прочитати CSV-файл.');
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            foreach (preg_split('/\r\n|\n|\r/', $content) as $lineNumber => $line) {
                if (! mb_check_encoding($line, 'UTF-8')) {
                    $this->fail($lineNumber + 1, 'Файл має бути у кодуванні UTF-8.');
                }
            }
        }

        $records = $this->records($content);

        if (count($records) < 2) {
            $this->fail(1, 'CSV має містити заголовок і хоча б один рядок даних.');
        }

        $headers = array_map(trim(...), $records[0]['fields']);

        if (count($headers) !== count(array_unique($headers))) {
            $this->fail(1, 'Заголовки колонок не можуть повторюватися.');
        }

        $required = ['word_en', 'word_uk', 'level', 'topic'];
        $missing = array_diff($required, $headers);

        if ($missing !== []) {
            $this->fail(1, 'Відсутні обов’язкові колонки: '.implode(', ', $missing).'.');
        }

        $positions = array_flip($headers);
        $extra = array_diff($headers, [...$required, 'lesson']);
        $warnings = $extra === [] ? [] : ['Проігноровано додаткові колонки: '.implode(', ', $extra).'.'];
        $topics = [];
        $pairs = [];
        $duplicateRows = 0;

        foreach (array_slice($records, 1) as $record) {
            $row = $record['row'];

            if (count($record['fields']) !== count($headers)) {
                $this->fail($row, 'Кількість клітинок не відповідає заголовку.');
            }

            $wordEn = $this->value($record['fields'][$positions['word_en']], $row, 'word_en', 191, true);
            $wordUk = $this->value($record['fields'][$positions['word_uk']], $row, 'word_uk', 191, true);
            $level = mb_strtoupper($this->value($record['fields'][$positions['level']], $row, 'level', 20), 'UTF-8');

            if (mb_strlen($level) > 20) {
                $this->fail($row, 'Колонка level перевищує 20 символів після нормалізації.');
            }

            $topic = $this->value($record['fields'][$positions['topic']], $row, 'topic', 160);
            $wordEnKey = TextKeyNormalizer::key($wordEn);
            $wordUkKey = TextKeyNormalizer::key($wordUk);
            $topicKey = TextKeyNormalizer::key($topic);
            $pairId = json_encode([$wordEnKey, $wordUkKey], JSON_THROW_ON_ERROR);
            $topicId = json_encode([$level, $topicKey], JSON_THROW_ON_ERROR);

            if (! isset($topics[$topicId])) {
                $topics[$topicId] = [
                    'level' => $level, 'name' => $topic, 'name_key' => $topicKey,
                    'row' => $row, 'pairs' => [],
                ];
            }

            if (isset($topics[$topicId]['pairs'][$pairId])) {
                $duplicateRows++;
            } else {
                $topics[$topicId]['pairs'][$pairId] = [
                    'word_en' => $wordEn, 'word_uk' => $wordUk,
                    'word_en_key' => $wordEnKey, 'word_uk_key' => $wordUkKey,
                ];
            }

            $pairs[$pairId] = true;
        }

        return [
            'rows' => count($records) - 1,
            'topics' => $topics,
            'unique_pairs' => count($pairs),
            'duplicate_rows' => $duplicateRows,
            'warnings' => $warnings,
        ];
    }

    /** @return list<array{row: int, fields: list<string>}> */
    private function records(string $content): array
    {
        $records = [];
        $fields = [];
        $field = '';
        $state = 'start';
        $row = 1;
        $recordRow = 1;
        $length = strlen($content);

        for ($index = 0; $index < $length; $index++) {
            $character = $content[$index];

            if ($character === '"') {
                if ($state === 'start') {
                    $state = 'quoted';
                } elseif ($state === 'quoted' && ($content[$index + 1] ?? null) === '"') {
                    $field .= '"';
                    $index++;
                } elseif ($state === 'quoted') {
                    $state = 'after_quote';
                } else {
                    $this->fail($row, 'Некоректні лапки у CSV.');
                }

                continue;
            }

            if ($character === ',' && $state !== 'quoted') {
                $fields[] = $field;
                $field = '';
                $state = 'start';

                continue;
            }

            if ($character === "\r" || $character === "\n") {
                if ($character === "\r") {
                    if (($content[$index + 1] ?? null) !== "\n") {
                        $this->fail($row, 'Некоректний перенос рядка у CSV.');
                    }

                    $index++;
                }

                if ($state === 'quoted') {
                    $field .= "\n";
                } else {
                    $fields[] = $field;
                    $records[] = ['row' => $recordRow, 'fields' => $fields];
                    $fields = [];
                    $field = '';
                    $state = 'start';
                    $recordRow = $row + 1;

                    if (count($records) > self::MAX_ROWS + 1) {
                        $this->fail($recordRow, 'Ліміт — 2 000 рядків даних.');
                    }
                }

                $row++;

                continue;
            }

            if ($state === 'after_quote') {
                $this->fail($row, 'Після закривальної лапки має бути кома або кінець рядка.');
            }

            $field .= $character;

            if ($state === 'start') {
                $state = 'plain';
            }
        }

        if ($state === 'quoted') {
            $this->fail($recordRow, 'Незакрите поле в лапках.');
        }

        if ($state !== 'start' || $fields !== []) {
            $fields[] = $field;
            $records[] = ['row' => $recordRow, 'fields' => $fields];
        }

        if (count($records) > self::MAX_ROWS + 1) {
            $this->fail($recordRow, 'Ліміт — 2 000 рядків даних.');
        }

        return $records;
    }

    private function value(string $raw, int $row, string $column, int $limit, bool $keyLimit = false): string
    {
        $value = TextKeyNormalizer::display($raw);

        if ($value === '') {
            $this->fail($row, "Колонка {$column} не може бути порожньою.");
        }

        if (mb_strlen($value) > $limit || ($keyLimit && mb_strlen(TextKeyNormalizer::key($value)) > $limit)) {
            $this->fail($row, "Колонка {$column} перевищує {$limit} символів після нормалізації.");
        }

        return $value;
    }

    private function fail(int $row, string $message): never
    {
        throw ValidationException::withMessages(['file' => "Рядок {$row}: {$message}"]);
    }
}
