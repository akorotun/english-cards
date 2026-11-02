<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use App\Models\Word;
use App\VocabularyCsvParser;
use App\VocabularyImportPreview;
use App\VocabularyImportUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VocabularyImportPreviewTest extends TestCase
{
    private array $temporaryFiles = [];

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

    public function test_real_sample_previews_180_rows_6_topics_179_words_and_180_links_without_writes(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $request = $this->request($user);
        $file = new UploadedFile(base_path('docs/temp/vocabulary-table-001.csv'), 'sample.csv', 'text/csv', null, true);

        $summary = app(VocabularyImportUpload::class)->preview($request, $file);

        $this->assertSame('private', $summary['scope']);
        $this->assertSame(180, $summary['rows']);
        $this->assertSame(6, $summary['topics']);
        $this->assertSame(179, $summary['unique_pairs']);
        $this->assertSame(180, $summary['unique_links']);
        $this->assertSame(0, $summary['duplicate_rows']);
        $this->assertSame(180, $summary['potential_new_links']);
        $this->assertTrue($summary['can_import']);
        $this->assertFileExists(app(VocabularyImportUpload::class)->resolve($request, $summary['token']));
        $this->assertSame(['topics' => 0, 'words' => 0, 'topic_word' => 0], $this->dictionaryCounts());
    }

    public function test_bom_reordered_columns_lesson_extras_crlf_and_quoted_fields_parse_correctly(): void
    {
        $csv = "\xEF\xBB\xBFtopic,extra,word_uk,lesson,level,word_en\r\n".
            "\"Food, \"\"hot\"\" drinks\",ignored,\"кава, чай\",99,a1-a2,\"Coffee\n drink\"\r\n".
            "\"Food, \"\"hot\"\" drinks\",ignored,\"кава, чай\",100,A1-A2,\"Coffee\n drink\"\r\n";
        $parsed = app(VocabularyCsvParser::class)->parse($this->file($csv)->getRealPath());

        $this->assertSame(2, $parsed['rows']);
        $this->assertCount(1, $parsed['topics']);
        $this->assertSame(1, $parsed['unique_pairs']);
        $this->assertSame(1, $parsed['duplicate_rows']);
        $this->assertCount(1, $parsed['warnings']);
        $group = array_values($parsed['topics'])[0];
        $this->assertSame('A1-A2', $group['level']);
        $this->assertSame('Food, "hot" drinks', $group['name']);
        $this->assertSame('Coffee drink', array_values($group['pairs'])[0]['word_en']);
        $this->assertSame('кава, чай', array_values($group['pairs'])[0]['word_uk']);
    }

    public function test_repeated_pair_in_two_topics_is_one_word_and_two_links(): void
    {
        $user = User::factory()->create();
        $request = $this->request($user);
        $csv = "word_en,word_uk,level,topic\nFriend,Друг,A1,People\n friend , друг ,a1,Other\n";

        $summary = app(VocabularyImportPreview::class)->inspect($request, $this->file($csv)->getRealPath());

        $this->assertSame(2, $summary['topics']);
        $this->assertSame(1, $summary['unique_pairs']);
        $this->assertSame(2, $summary['unique_links']);
        $this->assertSame(0, $summary['duplicate_rows']);
    }

    public function test_preview_counts_only_links_in_the_selected_owner_scope_without_exposing_foreign_usage(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $word = Word::factory()->create(['word_en' => 'Friend', 'word_uk' => 'друг']);
        $otherTopic = Topic::factory()->for($other)->create(['level' => 'A1', 'name' => 'People']);
        $otherTopic->words()->attach($word);
        $private = Topic::factory()->for($user)->create(['level' => 'A1', 'name' => 'People']);
        $system = Topic::factory()->create(['level' => 'A1', 'name' => 'People']);
        $system->words()->attach($word);
        $csv = "word_en,word_uk,level,topic\nFriend,друг,A1,People\n";
        $path = $this->file($csv)->getRealPath();

        $privateSummary = app(VocabularyImportPreview::class)->inspect($this->request($user), $path);
        config()->set('admin.emails', $user->email);
        $systemSummary = app(VocabularyImportPreview::class)->inspect($this->request($user, 'system'), $path);

        $this->assertSame(1, $privateSummary['reused_topics']);
        $this->assertSame(0, $privateSummary['existing_links']);
        $this->assertSame(1, $privateSummary['potential_new_links']);
        $this->assertSame(1, $systemSummary['existing_links']);
        $this->assertArrayNotHasKey('word_id', $privateSummary);
        $this->assertSame(['topics' => 3, 'words' => 1, 'topic_word' => 2], $this->dictionaryCounts());
        $this->assertModelExists($private);
    }

    public function test_ambiguous_target_topic_blocks_token_and_does_not_merge_or_write(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Topic::factory()->for($user)->create(['level' => 'a1', 'name' => '  FOOD  ']);
        Topic::factory()->for($user)->create(['level' => 'A1', 'name' => 'Food']);
        $request = $this->request($user);
        $csv = "word_en,word_uk,level,topic\nApple,яблуко,A1, food \n";

        $summary = app(VocabularyImportUpload::class)->preview($request, $this->file($csv));

        $this->assertFalse($summary['can_import']);
        $this->assertNull($summary['token']);
        $this->assertSame(2, $summary['ambiguities'][0]['row']);
        $this->assertNull($summary['potential_new_links']);
        $this->assertSame([], Storage::disk('local')->files('vocabulary-imports'));
        $this->assertSame(['topics' => 2, 'words' => 0, 'topic_word' => 0], $this->dictionaryCounts());
    }

    public function test_ordinary_user_cannot_preview_system_scope_even_with_client_admin_flag(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $request = $this->request($user, 'system');
        $request->merge(['is_admin' => true, 'user_id' => 999]);

        try {
            app(VocabularyImportUpload::class)->preview($request, $this->file("word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n"));
            $this->fail('System preview was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame([], Storage::disk('local')->files('vocabulary-imports'));
        $this->assertSame(['topics' => 0, 'words' => 0, 'topic_word' => 0], $this->dictionaryCounts());
    }

    public function test_invalid_csv_reports_physical_row_and_never_stores_it(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $request = $this->request($user);
        $invalidFiles = [
            ['word_en,word_uk,level,topic'."\n".'Apple,,A1,Food', 'Рядок 2'],
            ['word_en,word_uk,level,topic'."\n".'Apple,яблуко,A1', 'Рядок 2'],
            ['word_en,word_uk,level,topic'."\n".'"Apple,яблуко,A1,Food', 'Рядок 2'],
            ['word_en,word_uk,level,topic'."\n".'Apple,яблуко,A1,Food'."\n".'Broken"word,світ,A1,Food', 'Рядок 3'],
            ['word_en,word_uk,level,topic'."\n".'Apple,яблуко,A1,Food'."\n\n", 'Рядок 3'],
            ["word_en,word_uk,level,topic\nApple,\xFF,A1,Food", 'Рядок 2'],
            ["word_en,word_uk,level,topic\nApple,яблуко,".str_repeat('a', 21).',Food', 'Рядок 2'],
            ["word_en,word_uk,level,topic\n".str_repeat('ß', 96).',яблуко,A1,Food', 'Рядок 2'],
            ["word_en,word_uk,level,topic\nApple,яблуко,".str_repeat('ß', 11).',Food', 'Рядок 2'],
        ];

        foreach ($invalidFiles as [$csv, $expected]) {
            try {
                app(VocabularyImportUpload::class)->preview($request, $this->file($csv));
                $this->fail('Invalid CSV was accepted.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString($expected, $exception->errors()['file'][0]);
            }
        }

        $this->assertSame([], Storage::disk('local')->files('vocabulary-imports'));
        $this->assertSame(['topics' => 0, 'words' => 0, 'topic_word' => 0], $this->dictionaryCounts());
    }

    public function test_empty_missing_duplicate_headers_and_limits_are_rejected(): void
    {
        $parser = app(VocabularyCsvParser::class);
        $cases = [
            '',
            "word_en,word_uk,level,topic\n",
            "word_en,word_uk,level\nApple,яблуко,A1\n",
            "word_en,word_uk,level,topic,topic\nApple,яблуко,A1,Food,Food\n",
            str_repeat('a', VocabularyCsvParser::MAX_BYTES + 1),
            "word_en,word_uk,level,topic\n".str_repeat("Apple,яблуко,A1,Food\n", VocabularyCsvParser::MAX_ROWS + 1),
        ];

        foreach ($cases as $csv) {
            $this->expectValidation(fn () => $parser->parse($this->file($csv)->getRealPath()));
        }
    }

    public function test_token_is_bound_to_session_user_scope_hash_expiry_and_current_admin_permission(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        config()->set('admin.emails', $user->email);
        $request = $this->request($user, 'system');
        $upload = app(VocabularyImportUpload::class);
        $summary = $upload->preview($request, $this->file("word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n"));
        $token = $summary['token'];
        $path = $upload->resolve($request, $token);

        $this->expectValidation(fn () => $upload->resolve($this->request($other, 'private', $request->session()), $token));
        $this->expectValidation(fn () => $upload->resolve($this->request($user, 'private', $request->session()), $token));
        $this->expectValidation(fn () => $upload->resolve($this->request($user, 'system'), $token));
        Storage::disk('local')->put('vocabulary-imports/'.$token.'.csv', 'tampered');
        $this->expectValidation(fn () => $upload->resolve($request, $token));
        $this->assertFileExists($path);

        config()->set('admin.emails', '');
        try {
            $upload->resolve($request, $token);
            $this->fail('Revoked system permission was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_expired_preview_is_rejected_before_cleanup_and_scheduler_removes_file(): void
    {
        Storage::fake('local');
        $this->freezeTime();
        $user = User::factory()->create();
        $request = $this->request($user);
        $upload = app(VocabularyImportUpload::class);
        $summary = $upload->preview($request, $this->file("word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n"));
        $path = $upload->resolve($request, $summary['token']);

        $this->travel(30)->minutes();
        $this->expectValidation(fn () => $upload->resolve($request, $summary['token']));
        $this->assertFileExists($path);
        $this->assertSame(1, $upload->cleanupExpired());
        $this->assertFileDoesNotExist($path);
    }

    public function test_forgetting_a_token_removes_its_file_only_for_the_bound_session(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $request = $this->request($user);
        $upload = app(VocabularyImportUpload::class);
        $summary = $upload->preview($request, $this->file("word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n"));
        $path = $upload->resolve($request, $summary['token']);

        $upload->forget($this->request($user), $summary['token']);
        $this->assertFileExists($path);
        $upload->forget($request, $summary['token']);

        $this->assertFileDoesNotExist($path);
        $this->expectValidation(fn () => $upload->resolve($request, $summary['token']));
    }

    private function request(User $user, string $scope = 'private', ?Store $session = null): Request
    {
        $request = Request::create('/vocabulary/import/preview', 'POST', ['scope' => $scope]);
        $request->setUserResolver(fn () => $user);
        $session ??= new Store('test', new ArraySessionHandler(60));
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }

    private function file(string $csv): UploadedFile
    {
        $file = UploadedFile::fake()->createWithContent('words.csv', $csv);
        $this->temporaryFiles[] = $file;

        return $file;
    }

    /** @return array{topics: int, words: int, topic_word: int} */
    private function dictionaryCounts(): array
    {
        return [
            'topics' => Topic::count(),
            'words' => Word::count(),
            'topic_word' => DB::table('topic_word')->count(),
        ];
    }

    private function expectValidation(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected CSV validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors()['file'] ?? []);
        }
    }
}
