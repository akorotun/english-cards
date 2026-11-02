<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TopicTest extends TestCase
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

    public function test_topics_schema_has_owner_level_indexes_and_cascading_foreign_key(): void
    {
        $this->assertTrue(Schema::hasColumns('topics', ['id', 'user_id', 'level', 'name', 'created_at', 'updated_at']));
        $this->assertFalse(Schema::hasColumn('topics', 'name_key'));
        $this->assertFalse(Schema::hasColumn('topics', 'scope_owner_id'));

        $columns = DB::select("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'topics'");
        $columnsByName = collect($columns)->keyBy('COLUMN_NAME');

        $this->assertSame('YES', $columnsByName['user_id']->IS_NULLABLE);
        $this->assertSame('varchar', $columnsByName['level']->DATA_TYPE);
        $this->assertSame(20, $columnsByName['level']->CHARACTER_MAXIMUM_LENGTH);
        $this->assertSame('varchar', $columnsByName['name']->DATA_TYPE);
        $this->assertSame(160, $columnsByName['name']->CHARACTER_MAXIMUM_LENGTH);
        $this->assertSame('NO', $columnsByName['name']->IS_NULLABLE);

        $indexes = DB::select("SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'topics'");
        $indexesByName = collect($indexes)->keyBy('INDEX_NAME');
        $this->assertArrayHasKey('topics_user_id_index', $indexesByName);
        $this->assertArrayHasKey('topics_level_index', $indexesByName);
        $this->assertCount(1, collect($indexes)->filter(fn (object $index): bool => $index->NON_UNIQUE === 0));

        $foreignKey = DB::selectOne("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'topics' AND CONSTRAINT_NAME = 'topics_user_id_foreign'");
        $this->assertSame('CASCADE', $foreignKey->DELETE_RULE);
    }

    public function test_identical_names_are_allowed_for_system_topics_and_one_owners_topics(): void
    {
        $user = User::factory()->create();
        $firstSystem = Topic::factory()->create(['level' => 'A1-A2', 'name' => 'Animals']);
        $secondSystem = Topic::factory()->create(['level' => 'A1-A2', 'name' => 'Animals']);
        $firstPrivate = Topic::factory()->for($user)->create(['level' => 'A1-A2', 'name' => 'Animals']);
        $secondPrivate = Topic::factory()->for($user)->create(['level' => 'A1-A2', 'name' => 'Animals']);

        $this->assertNotSame($firstSystem->id, $secondSystem->id);
        $this->assertNotSame($firstPrivate->id, $secondPrivate->id);
        $this->assertSame(4, Topic::where('name', 'Animals')->where('level', 'A1-A2')->count());
        $this->assertNull($firstSystem->user);
        $this->assertTrue($firstPrivate->user->is($user));
        $this->assertEqualsCanonicalizing([$firstPrivate->id, $secondPrivate->id], $user->topics->modelKeys());
    }

    public function test_names_can_repeat_across_owners_and_levels_and_level_case_is_stable(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $first = Topic::factory()->for($firstUser)->create(['level' => ' a1-a2 ', 'name' => '  Food  ']);
        $second = Topic::factory()->for($secondUser)->create(['level' => 'A1-A2', 'name' => 'Food']);
        $third = Topic::factory()->for($firstUser)->create(['level' => 'b1', 'name' => 'Food']);

        $this->assertSame('A1-A2', $first->refresh()->level);
        $this->assertSame('Food', $first->name);
        $this->assertSame('B1', $third->level);
        $this->assertCount(2, $firstUser->topics);
        $this->assertCount(1, $secondUser->topics);
        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->id, $third->id);
    }

    public function test_deleting_a_user_cascades_only_their_private_topics(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $system = Topic::factory()->create(['name' => 'Food']);
        $firstPrivate = Topic::factory()->for($firstUser)->create(['name' => 'Food']);
        $secondPrivate = Topic::factory()->for($secondUser)->create(['name' => 'Food']);

        $firstUser->delete();

        $this->assertDatabaseMissing('topics', ['id' => $firstPrivate->id]);
        $this->assertDatabaseHas('topics', ['id' => $system->id, 'user_id' => null]);
        $this->assertDatabaseHas('topics', ['id' => $secondPrivate->id, 'user_id' => $secondUser->id]);
    }

    public function test_foreign_key_rejects_an_unknown_owner(): void
    {
        $this->expectException(QueryException::class);

        Topic::factory()->create(['user_id' => 999999]);
    }

    public function test_blank_names_and_levels_are_rejected_before_insert(): void
    {
        foreach ([['level' => '  ', 'name' => 'Food'], ['level' => 'B1', 'name' => "\u{00A0}\t"]] as $values) {
            try {
                Topic::factory()->create($values);
                $this->fail('Blank topic content was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertSame(0, Topic::count());
    }
}
