<?php

namespace Tests\Feature;

use App\Models\Word;
use App\TextKeyNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WordTest extends TestCase
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

    public function test_words_have_the_global_dictionary_schema(): void
    {
        $this->assertTrue(Schema::hasColumns('words', [
            'id', 'word_en', 'word_uk', 'word_en_key', 'word_uk_key',
            'image_path', 'example_en', 'example_uk', 'created_at', 'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('words', 'user_id'));
        $this->assertFalse(Schema::hasColumn('words', 'profile_id'));
        $this->assertFalse(Schema::hasColumn('words', 'is_system'));

        $columns = DB::select("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'words'");
        $columnsByName = collect($columns)->keyBy('COLUMN_NAME');

        foreach (['word_en', 'word_uk', 'word_en_key', 'word_uk_key'] as $columnName) {
            $this->assertSame('varchar', $columnsByName[$columnName]->DATA_TYPE);
            $this->assertSame(191, $columnsByName[$columnName]->CHARACTER_MAXIMUM_LENGTH);
            $this->assertSame('NO', $columnsByName[$columnName]->IS_NULLABLE);
        }

        foreach (['word_en_key', 'word_uk_key'] as $columnName) {
            $this->assertSame('utf8mb4_bin', $columnsByName[$columnName]->COLLATION_NAME);
        }

        $this->assertSame('YES', $columnsByName['image_path']->IS_NULLABLE);
        $this->assertSame('YES', $columnsByName['example_en']->IS_NULLABLE);
        $this->assertSame('YES', $columnsByName['example_uk']->IS_NULLABLE);

        $indexColumns = DB::select("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'words' AND INDEX_NAME = 'words_word_en_key_word_uk_key_unique' AND NON_UNIQUE = 0 ORDER BY SEQ_IN_INDEX");
        $this->assertSame(['word_en_key', 'word_uk_key'], array_column($indexColumns, 'COLUMN_NAME'));
    }

    public function test_equivalent_words_have_the_same_keys_and_cannot_be_inserted_twice(): void
    {
        $first = Word::factory()->create(['word_en' => 'Apple', 'word_uk' => 'Яблуко']);

        $this->assertSame('apple', $first->word_en_key);
        $this->assertSame('яблуко', $first->word_uk_key);
        $this->assertSame('apple', TextKeyNormalizer::key(' apple  '));

        $this->expectException(QueryException::class);

        Word::factory()->create(['word_en' => ' apple  ', 'word_uk' => ' яблуко ']);
    }

    public function test_one_english_word_can_have_multiple_ukrainian_translations(): void
    {
        $light = Word::factory()->create(['word_en' => 'light', 'word_uk' => 'світло']);
        $easy = Word::factory()->create(['word_en' => 'light', 'word_uk' => 'легкий']);

        $this->assertNotSame($light->id, $easy->id);
        $this->assertSame($light->word_en_key, $easy->word_en_key);
        $this->assertNotSame($light->word_uk_key, $easy->word_uk_key);
        $this->assertSame(2, Word::count());
    }

    public function test_model_save_normalizes_unicode_whitespace_and_recomputes_keys_on_update(): void
    {
        $word = Word::factory()->create([
            'word_en' => "  Cafe\u{301}\u{00A0}  au\t lait  ",
            'word_uk' => '  Кава  з молоком ',
            'image_path' => 'words/coffee.jpg',
            'example_en' => 'Coffee is warm.',
            'example_uk' => 'Кава тепла.',
        ]);

        $this->assertSame('Café au lait', $word->word_en);
        $this->assertSame('café au lait', $word->word_en_key);
        $this->assertSame($word->word_en_key, TextKeyNormalizer::key('  CAFÉ   au lait '));
        $this->assertSame('Кава з молоком', $word->word_uk);
        $this->assertSame('кава з молоком', $word->word_uk_key);
        $this->assertSame('words/coffee.jpg', $word->refresh()->image_path);

        $word->update(['word_en' => '  LIGHT  ']);

        $this->assertSame('LIGHT', $word->refresh()->word_en);
        $this->assertSame('light', $word->word_en_key);
    }

    public function test_blank_words_and_translations_are_rejected_before_database_insert(): void
    {
        foreach ([['word_en' => '  ', 'word_uk' => 'яблуко'], ['word_en' => 'Apple', 'word_uk' => "\u{00A0}\t"]] as $values) {
            try {
                Word::factory()->create($values);
                $this->fail('Blank card content was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertSame(0, Word::count());
    }

    public function test_normalized_words_longer_than_the_column_limit_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Word::factory()->create(['word_en' => str_repeat('a', 192), 'word_uk' => 'яблуко']);
    }
}
