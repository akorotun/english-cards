<?php

namespace Tests\Feature;

use App\Models\LearningProfile;
use App\Models\Topic;
use App\Models\User;
use App\Models\Word;
use App\Models\WordProgress;
use App\VocabularyImporter;
use App\VocabularyImportUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VocabularyImporterTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_real_sample_imports_once_and_reuses_all_entities_on_reimport(): void
    {
        $request = $this->request(User::factory()->create());
        $file = new UploadedFile(base_path('docs/temp/vocabulary-table-001.csv'), 'sample.csv', 'text/csv', null, true);
        $upload = app(VocabularyImportUpload::class);
        $importer = app(VocabularyImporter::class);
        $first = $upload->preview($request, $file);
        $report = $importer->confirm($request, $first['token']);

        $this->assertSame(['rows' => 180, 'topics' => 6, 'unique_pairs' => 179, 'unique_links' => 180],
            array_intersect_key($report, array_flip(['rows', 'topics', 'unique_pairs', 'unique_links'])));
        $this->assertSame(6, $report['new_topics']);
        $this->assertSame(179, $report['new_words']);
        $this->assertSame(180, $report['new_links']);
        $this->assertSame([$request->user()->id], Topic::distinct()->pluck('user_id')->all());
        $this->assertSame(180, DB::table('topic_word')->count());
        $this->assertSame([], Storage::disk('local')->files('vocabulary-imports'));
        $this->expectValidation(fn () => $importer->confirm($request, $first['token']));

        $again = $upload->preview($request, $file);
        $repeated = $importer->confirm($request, $again['token']);
        $this->assertSame(0, $repeated['new_topics']);
        $this->assertSame(0, $repeated['new_words']);
        $this->assertSame(0, $repeated['new_links']);
        $this->assertSame(180, $repeated['reused_links']);
        $this->assertSame(6, Topic::count());
        $this->assertSame(179, Word::count());
    }

    public function test_admin_private_and_system_imports_share_words_but_keep_topics_separate(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();
        config()->set('admin.emails', $admin->email);
        $csv = "word_en,word_uk,level,topic\nLight,світло,A1,Objects\nLight,легкий,A1,Objects\n";
        $upload = app(VocabularyImportUpload::class);
        $importer = app(VocabularyImporter::class);
        $privateRequest = $this->request($admin);
        $privateToken = $upload->preview($privateRequest, $this->file($csv))['token'];
        $privateReport = $importer->confirm($privateRequest, $privateToken);
        $systemRequest = $this->request($admin, 'system');
        $systemToken = $upload->preview($systemRequest, $this->file($csv))['token'];
        $systemReport = $importer->confirm($systemRequest, $systemToken);
        $otherRequest = $this->request($other);
        $otherToken = $upload->preview($otherRequest, $this->file($csv))['token'];
        $otherReport = $importer->confirm($otherRequest, $otherToken);

        $this->assertSame('private', $privateReport['scope']);
        $this->assertSame('system', $systemReport['scope']);
        $this->assertSame(0, $systemReport['new_words']);
        $this->assertSame(0, $otherReport['new_words']);
        $this->assertSame(3, Topic::count());
        $this->assertSame(2, Word::count());
        $this->assertSame(6, DB::table('topic_word')->count());
        $this->assertSame([null, $admin->id, $other->id], Topic::orderBy('id')->pluck('user_id')->sort()->values()->all());
    }

    public function test_rechecks_scope_token_expiry_and_current_permissions_before_writes(): void
    {
        $admin = User::factory()->create();
        $ordinary = User::factory()->create();
        config()->set('admin.emails', $admin->email);
        $upload = app(VocabularyImportUpload::class);
        $importer = app(VocabularyImporter::class);
        $request = $this->request($admin, 'system');
        $token = $upload->preview($request, $this->file($this->csv()))['token'];

        $this->expectValidation(fn () => $importer->confirm($this->request($ordinary, 'private', $request->session()), $token));
        $this->expectValidation(fn () => $importer->confirm($this->request($admin, 'private', $request->session()), $token));
        $this->expectValidation(fn () => $importer->confirm($this->request($admin, 'system'), $token));
        config()->set('admin.emails', '');

        try {
            $importer->confirm($request, $token);
            $this->fail('Revoked admin access was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $ordinaryRequest = $this->request($ordinary, 'system');

        try {
            $importer->confirm($ordinaryRequest, $token);
            $this->fail('System import was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(0, Topic::count());
        $this->assertSame(0, Word::count());
    }

    public function test_tampered_expired_and_now_ambiguous_previews_cannot_write(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $request = $this->request($user);
        $upload = app(VocabularyImportUpload::class);
        $importer = app(VocabularyImporter::class);
        $token = $upload->preview($request, $this->file($this->csv()))['token'];
        Storage::disk('local')->put('vocabulary-imports/'.$token.'.csv', 'changed');
        $this->expectValidation(fn () => $importer->confirm($request, $token));

        $token = $upload->preview($request, $this->file($this->csv()))['token'];
        $this->travel(30)->minutes();
        $this->expectValidation(fn () => $importer->confirm($request, $token));

        $token = $upload->preview($request, $this->file($this->csv()))['token'];
        Topic::factory()->for($user)->create(['level' => 'A1', 'name' => 'Food']);
        Topic::factory()->for($user)->create(['level' => 'A1', 'name' => ' FOOD ']);
        $this->expectValidation(fn () => $importer->confirm($request, $token));
        $this->assertSame(2, Topic::count());
        $this->assertSame(0, Word::count());
    }

    public function test_rollback_preserves_existing_word_fields_and_progress(): void
    {
        $user = User::factory()->create();
        $profile = LearningProfile::factory()->for($user)->create();
        $word = Word::factory()->create([
            'word_en' => 'Apple', 'word_uk' => 'яблуко',
            'image_path' => 'kept.jpg', 'example_en' => 'Kept example',
        ]);
        $progress = WordProgress::factory()->create([
            'learning_profile_id' => $profile->id, 'word_id' => $word->id, 'knowledge_level' => 3,
        ]);
        $request = $this->request($user);
        $csv = "word_en,word_uk,level,topic\nApple,яблуко,A1,Food\nBanana,банан,A1,Food\n";
        $upload = app(VocabularyImportUpload::class);
        $token = $upload->preview($request, $this->file($csv))['token'];
        $listener = function (Word $savingWord): void {
            if ($savingWord->word_en === 'Banana') {
                throw new RuntimeException('Simulated write failure');
            }
        };
        Word::saving($listener);

        try {
            app(VocabularyImporter::class)->confirm($request, $token);
            $this->fail('Expected transaction rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated write failure', $exception->getMessage());
        } finally {
            Word::flushEventListeners();
        }

        $this->assertSame(0, Topic::count());
        $this->assertSame(1, Word::count());
        $this->assertSame(0, DB::table('topic_word')->count());
        $this->assertSame('kept.jpg', $word->fresh()->image_path);
        $this->assertSame('Kept example', $word->fresh()->example_en);
        $this->assertSame(3, $progress->fresh()->knowledge_level);
        $this->assertFileExists($upload->resolve($request, $token));
    }

    public function test_normalized_duplicates_and_repeated_rows_do_not_duplicate_links(): void
    {
        $user = User::factory()->create();
        $request = $this->request($user);
        $csv = "word_en,word_uk,level,topic\nFriend,Друг,A1,People\n friend , друг ,a1, PEOPLE \nFriend,Друг,A1,Others\n";
        $upload = app(VocabularyImportUpload::class);
        $token = $upload->preview($request, $this->file($csv))['token'];
        $report = app(VocabularyImporter::class)->confirm($request, $token);

        $this->assertSame(3, $report['rows']);
        $this->assertSame(1, $report['duplicate_rows']);
        $this->assertSame(1, $report['unique_pairs']);
        $this->assertSame(2, $report['new_links']);
        $this->assertSame(1, Word::count());
        $this->assertSame(2, DB::table('topic_word')->count());
    }

    public function test_confirm_uses_current_topic_and_word_without_changing_shared_content_or_progress(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $request = $this->request($user);
        $upload = app(VocabularyImportUpload::class);
        $token = $upload->preview($request, $this->file($this->csv()))['token'];
        $word = Word::factory()->create([
            'word_en' => 'APPLE', 'word_uk' => 'ЯБЛУКО',
            'image_path' => 'shared.jpg', 'example_en' => 'Shared sentence',
        ]);
        $otherTopic = Topic::factory()->for($other)->create(['level' => 'A1', 'name' => 'Food']);
        $otherTopic->words()->attach($word);
        $target = Topic::factory()->for($user)->create(['level' => 'A1', 'name' => ' FOOD ']);
        $profile = LearningProfile::factory()->for($other)->create();
        $progress = WordProgress::factory()->create([
            'learning_profile_id' => $profile->id, 'word_id' => $word->id, 'knowledge_level' => 2,
        ]);

        $report = app(VocabularyImporter::class)->confirm($request, $token);

        $this->assertSame(0, $report['new_topics']);
        $this->assertSame(0, $report['new_words']);
        $this->assertSame(1, $report['new_links']);
        $this->assertDatabaseHas('topic_word', ['topic_id' => $target->id, 'word_id' => $word->id]);
        $this->assertSame('shared.jpg', $word->fresh()->image_path);
        $this->assertSame('Shared sentence', $word->fresh()->example_en);
        $this->assertSame(2, $progress->fresh()->knowledge_level);
        $this->assertSame(2, Topic::count());
        $this->assertSame(1, Word::count());
    }

    public function test_topic_advisory_lock_is_held_during_create_and_released_afterward(): void
    {
        $user = User::factory()->create();
        $request = $this->request($user);
        $upload = app(VocabularyImportUpload::class);
        $token = $upload->preview($request, $this->file($this->csv()))['token'];
        $identity = json_encode([$user->id, 'A1', 'food'], JSON_THROW_ON_ERROR);
        $lock = 'vti:'.substr(hash('sha256', $identity), 0, 60);
        config()->set('database.connections.mysql_probe', config('database.connections.mysql'));
        $probe = DB::connection('mysql_probe');
        $lockObserved = false;

        Topic::creating(function () use ($probe, $lock, &$lockObserved): void {
            $lockObserved = (int) $probe->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired === 0;
        });

        try {
            app(VocabularyImporter::class)->confirm($request, $token);
        } finally {
            Topic::flushEventListeners();
            $probe->disconnect();
        }

        $this->assertTrue($lockObserved);
        $this->assertSame(1, (int) DB::selectOne('SELECT IS_FREE_LOCK(?) AS free', [$lock])->free);
    }

    public function test_word_unique_conflict_reuses_the_existing_row(): void
    {
        $request = $this->request(User::factory()->create());
        $upload = app(VocabularyImportUpload::class);
        $token = $upload->preview($request, $this->file($this->csv()))['token'];
        $inserted = false;

        Word::creating(function (Word $word) use (&$inserted): void {
            if ($inserted) {
                return;
            }

            $inserted = true;
            DB::table('words')->insert([
                'word_en' => $word->word_en,
                'word_uk' => $word->word_uk,
                'word_en_key' => $word->word_en_key,
                'word_uk_key' => $word->word_uk_key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $report = app(VocabularyImporter::class)->confirm($request, $token);
        } finally {
            Word::flushEventListeners();
        }

        $this->assertSame(0, $report['new_words']);
        $this->assertSame(1, $report['reused_words']);
        $this->assertSame(1, Word::count());
        $this->assertSame(1, DB::table('topic_word')->count());
    }

    private function csv(): string
    {
        return "word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n";
    }

    private function file(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('words.csv', $csv);
    }

    private function request(User $user, string $scope = 'private', ?Store $session = null): Request
    {
        $request = Request::create('/vocabulary/import/confirm', 'POST', ['scope' => $scope]);
        $request->setUserResolver(fn () => $user);
        $session ??= new Store('test', new ArraySessionHandler(60));
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }

    private function expectValidation(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected validation error.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors()['file'] ?? []);
        }
    }
}
