<?php

namespace Tests\Feature;

use App\Models\LearningProfile;
use App\Models\ProgressSyncBatch;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProgressSyncBatchTest extends TestCase
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

    public function test_batch_schema_has_uuid_primary_key_foreign_key_and_composite_index(): void
    {
        $this->assertTrue(Schema::hasColumns('progress_sync_batches', [
            'session_uuid', 'learning_profile_id', 'payload_hash', 'result_json', 'processed_at',
        ]));
        $this->assertFalse(Schema::hasColumn('progress_sync_batches', 'id'));
        $this->assertFalse(Schema::hasColumn('progress_sync_batches', 'created_at'));
        $this->assertFalse(Schema::hasColumn('progress_sync_batches', 'updated_at'));

        $columns = DB::select("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'progress_sync_batches'");
        $columnsByName = collect($columns)->keyBy('COLUMN_NAME');
        $this->assertSame('char', $columnsByName['session_uuid']->DATA_TYPE);
        $this->assertSame(36, $columnsByName['session_uuid']->CHARACTER_MAXIMUM_LENGTH);
        $this->assertSame('NO', $columnsByName['session_uuid']->IS_NULLABLE);
        $this->assertSame('char', $columnsByName['payload_hash']->DATA_TYPE);
        $this->assertSame(64, $columnsByName['payload_hash']->CHARACTER_MAXIMUM_LENGTH);
        $this->assertSame('NO', $columnsByName['payload_hash']->IS_NULLABLE);
        $this->assertSame('json', $columnsByName['result_json']->DATA_TYPE);
        $this->assertSame('YES', $columnsByName['result_json']->IS_NULLABLE);
        $this->assertSame('timestamp', $columnsByName['processed_at']->DATA_TYPE);
        $this->assertSame('NO', $columnsByName['processed_at']->IS_NULLABLE);

        $primaryKey = DB::select("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'progress_sync_batches' AND INDEX_NAME = 'PRIMARY' ORDER BY SEQ_IN_INDEX");
        $this->assertSame(['session_uuid'], array_column($primaryKey, 'COLUMN_NAME'));

        $index = DB::select("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'progress_sync_batches' AND INDEX_NAME = 'progress_sync_batches_learning_profile_id_processed_at_index' ORDER BY SEQ_IN_INDEX");
        $this->assertSame(['learning_profile_id', 'processed_at'], array_column($index, 'COLUMN_NAME'));

        $foreignKey = DB::selectOne("SELECT k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE AS k JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r USING (CONSTRAINT_SCHEMA, CONSTRAINT_NAME) WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = 'progress_sync_batches' AND k.COLUMN_NAME = 'learning_profile_id'");
        $this->assertSame('learning_profiles', $foreignKey->REFERENCED_TABLE_NAME);
        $this->assertSame('id', $foreignKey->REFERENCED_COLUMN_NAME);
        $this->assertSame('CASCADE', $foreignKey->DELETE_RULE);
    }

    public function test_batch_round_trips_uuid_hash_json_and_processed_time_through_model(): void
    {
        $profile = LearningProfile::factory()->create();
        $processedAt = now()->startOfSecond();
        $hash = hash('sha256', 'canonical payload');
        $result = ['applied' => 2, 'skipped_word_ids' => [7]];

        $batch = ProgressSyncBatch::factory()->for($profile, 'learningProfile')->create([
            'payload_hash' => $hash,
            'result_json' => $result,
            'processed_at' => $processedAt,
        ]);
        $stored = ProgressSyncBatch::findOrFail($batch->session_uuid);

        $this->assertSame($batch->session_uuid, $stored->getKey());
        $this->assertFalse($stored->getIncrementing());
        $this->assertSame('string', $stored->getKeyType());
        $this->assertSame($hash, $stored->payload_hash);
        $this->assertSame($result, $stored->result_json);
        $this->assertTrue($processedAt->equalTo($stored->processed_at));
        $this->assertTrue($stored->learningProfile->is($profile));
        $this->assertCount(1, $profile->progressSyncBatches);
    }

    public function test_duplicate_session_uuid_is_rejected_even_for_another_profile(): void
    {
        $first = ProgressSyncBatch::factory()->create();
        $otherProfile = LearningProfile::factory()->create();

        $this->expectException(QueryException::class);

        ProgressSyncBatch::factory()->for($otherProfile, 'learningProfile')->create([
            'session_uuid' => $first->session_uuid,
        ]);
    }

    public function test_batch_requires_existing_profile(): void
    {
        $this->expectException(QueryException::class);

        ProgressSyncBatch::factory()->create(['learning_profile_id' => 999999]);
    }

    public function test_deleting_profile_cascades_its_batches_only(): void
    {
        $firstProfile = LearningProfile::factory()->create();
        $secondProfile = LearningProfile::factory()->create();
        $first = ProgressSyncBatch::factory()->for($firstProfile, 'learningProfile')->create();
        $second = ProgressSyncBatch::factory()->for($secondProfile, 'learningProfile')->create();

        DB::table('learning_profiles')->where('id', $firstProfile->id)->delete();

        $this->assertDatabaseMissing('progress_sync_batches', ['session_uuid' => $first->session_uuid]);
        $this->assertDatabaseHas('progress_sync_batches', ['session_uuid' => $second->session_uuid]);
    }
}
