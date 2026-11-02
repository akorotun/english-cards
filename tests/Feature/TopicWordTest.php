<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\Word;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TopicWordTest extends TestCase
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

    public function test_pivot_has_composite_primary_key_word_index_and_cascading_foreign_keys(): void
    {
        $this->assertSame(['topic_id', 'word_id'], Schema::getColumnListing('topic_word'));

        $primaryColumns = DB::select("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'topic_word' AND INDEX_NAME = 'PRIMARY' ORDER BY SEQ_IN_INDEX");
        $this->assertSame(['topic_id', 'word_id'], array_column($primaryColumns, 'COLUMN_NAME'));

        $wordIndex = DB::selectOne("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'topic_word' AND INDEX_NAME = 'topic_word_word_id_index'");
        $this->assertNotNull($wordIndex);

        $foreignKeys = DB::select("SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'topic_word'");
        $this->assertSame([
            'topic_word_topic_id_foreign' => 'CASCADE',
            'topic_word_word_id_foreign' => 'CASCADE',
        ], collect($foreignKeys)->pluck('DELETE_RULE', 'CONSTRAINT_NAME')->all());
    }

    public function test_one_word_can_belong_to_multiple_topics_without_a_duplicate_word(): void
    {
        $word = Word::factory()->create(['word_en' => 'apple', 'word_uk' => 'яблуко']);
        $firstTopic = Topic::factory()->create();
        $secondTopic = Topic::factory()->create();

        $firstTopic->words()->attach($word);
        $secondTopic->words()->attach($word);

        $this->assertSame(1, Word::count());
        $this->assertCount(2, $word->topics);
        $this->assertTrue($firstTopic->words->first()->is($word));
        $this->assertTrue($secondTopic->words->first()->is($word));
        $this->assertSame(2, DB::table('topic_word')->where('word_id', $word->id)->count());
    }

    public function test_duplicate_topic_word_pair_is_rejected(): void
    {
        $topic = Topic::factory()->create();
        $word = Word::factory()->create();
        $topic->words()->attach($word);

        $this->expectException(QueryException::class);

        $topic->words()->attach($word);
    }

    public function test_direct_deletes_cascade_pivot_rows_but_do_not_delete_orphaned_words(): void
    {
        $sharedWord = Word::factory()->create();
        $orphanWord = Word::factory()->create();
        $firstTopic = Topic::factory()->create();
        $secondTopic = Topic::factory()->create();
        $firstTopic->words()->attach([$sharedWord->id, $orphanWord->id]);
        $secondTopic->words()->attach($sharedWord);

        DB::table('topics')->where('id', $firstTopic->id)->delete();

        $this->assertSame(1, DB::table('topic_word')->count());
        $this->assertDatabaseHas('words', ['id' => $sharedWord->id]);
        $this->assertDatabaseHas('words', ['id' => $orphanWord->id]);
        $this->assertDatabaseMissing('topic_word', ['topic_id' => $firstTopic->id]);

        DB::table('words')->where('id', $sharedWord->id)->delete();

        $this->assertSame(0, DB::table('topic_word')->count());
        $this->assertDatabaseHas('topics', ['id' => $secondTopic->id]);
    }
}
