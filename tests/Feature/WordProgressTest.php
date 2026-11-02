<?php

namespace Tests\Feature;

use App\Models\LearningProfile;
use App\Models\User;
use App\Models\Word;
use App\Models\WordProgress;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WordProgressTest extends TestCase
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

    public function test_progress_schema_has_unique_pair_cascading_foreign_keys_and_check(): void
    {
        $this->assertTrue(Schema::hasColumns('word_progress', [
            'id', 'learning_profile_id', 'word_id', 'knowledge_level', 'created_at', 'updated_at',
        ]));

        $column = DB::selectOne("SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'word_progress' AND COLUMN_NAME = 'knowledge_level'");
        $this->assertSame('tinyint', $column->DATA_TYPE);
        $this->assertSame('tinyint unsigned', $column->COLUMN_TYPE);
        $this->assertSame('NO', $column->IS_NULLABLE);
        $this->assertSame('0', (string) $column->COLUMN_DEFAULT);

        $uniqueColumns = DB::select("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'word_progress' AND INDEX_NAME = 'word_progress_learning_profile_id_word_id_unique' AND NON_UNIQUE = 0 ORDER BY SEQ_IN_INDEX");
        $this->assertSame(['learning_profile_id', 'word_id'], array_column($uniqueColumns, 'COLUMN_NAME'));

        $wordIndex = DB::selectOne("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'word_progress' AND INDEX_NAME = 'word_progress_word_id_index'");
        $this->assertNotNull($wordIndex);

        $foreignKeys = DB::select("SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'word_progress'");
        $this->assertSame([
            'word_progress_learning_profile_id_foreign' => 'CASCADE',
            'word_progress_word_id_foreign' => 'CASCADE',
        ], collect($foreignKeys)->pluck('DELETE_RULE', 'CONSTRAINT_NAME')->all());

        $check = DB::selectOne("SELECT CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'word_progress' AND CONSTRAINT_NAME = 'word_progress_knowledge_level_check'");
        $this->assertSame('CHECK', $check->CONSTRAINT_TYPE);
    }

    public function test_two_profiles_have_independent_progress_for_the_same_word(): void
    {
        $user = User::factory()->create();
        $firstProfile = LearningProfile::factory()->for($user)->create();
        $secondProfile = LearningProfile::factory()->for($user)->create();
        $word = Word::factory()->create();
        $firstProgress = WordProgress::factory()->create([
            'learning_profile_id' => $firstProfile->id,
            'word_id' => $word->id,
            'knowledge_level' => 1,
        ]);
        $secondProgress = WordProgress::factory()->create([
            'learning_profile_id' => $secondProfile->id,
            'word_id' => $word->id,
            'knowledge_level' => 3,
        ]);

        $this->assertSame(1, $firstProgress->refresh()->knowledge_level);
        $this->assertSame(3, $secondProgress->refresh()->knowledge_level);
        $this->assertTrue($firstProgress->learningProfile->is($firstProfile));
        $this->assertTrue($secondProgress->word->is($word));
        $this->assertCount(1, $firstProfile->wordProgress);
        $this->assertCount(1, $secondProfile->wordProgress);
        $this->assertCount(2, $word->progress);
    }

    public function test_duplicate_progress_for_one_profile_and_word_is_rejected(): void
    {
        $profile = LearningProfile::factory()->create();
        $word = Word::factory()->create();
        WordProgress::factory()->create(['learning_profile_id' => $profile->id, 'word_id' => $word->id]);

        $this->expectException(QueryException::class);

        WordProgress::factory()->create(['learning_profile_id' => $profile->id, 'word_id' => $word->id]);
    }

    public function test_missing_progress_has_no_row_and_inserted_progress_defaults_to_zero(): void
    {
        $profile = LearningProfile::factory()->create();
        $word = Word::factory()->create();

        $this->assertNull($profile->wordProgress()->where('word_id', $word->id)->first());

        DB::table('word_progress')->insert([
            'learning_profile_id' => $profile->id,
            'word_id' => $word->id,
        ]);

        $this->assertSame(0, $profile->wordProgress()->first()->knowledge_level);
    }

    public function test_database_check_rejects_knowledge_level_above_three(): void
    {
        $profile = LearningProfile::factory()->create();
        $word = Word::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('word_progress')->insert([
            'learning_profile_id' => $profile->id,
            'word_id' => $word->id,
            'knowledge_level' => 4,
        ]);
    }

    public function test_direct_profile_and_word_deletes_cascade_only_their_progress(): void
    {
        $firstProfile = LearningProfile::factory()->create();
        $secondProfile = LearningProfile::factory()->create();
        $firstWord = Word::factory()->create();
        $secondWord = Word::factory()->create();
        $first = WordProgress::factory()->create(['learning_profile_id' => $firstProfile->id, 'word_id' => $firstWord->id]);
        $second = WordProgress::factory()->create(['learning_profile_id' => $secondProfile->id, 'word_id' => $firstWord->id]);
        $third = WordProgress::factory()->create(['learning_profile_id' => $secondProfile->id, 'word_id' => $secondWord->id]);

        DB::table('learning_profiles')->where('id', $firstProfile->id)->delete();

        $this->assertDatabaseMissing('word_progress', ['id' => $first->id]);
        $this->assertDatabaseHas('word_progress', ['id' => $second->id]);
        $this->assertDatabaseHas('word_progress', ['id' => $third->id]);

        DB::table('words')->where('id', $firstWord->id)->delete();

        $this->assertDatabaseMissing('word_progress', ['id' => $second->id]);
        $this->assertDatabaseHas('word_progress', ['id' => $third->id]);
    }
}
