<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VocabularyImportHttpTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshIsolatedDatabase;
    }

    public function refreshDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('english_cards_test', config('database.connections.mysql.database'));
        $this->refreshIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('admin.emails', '');
    }

    public function test_guest_and_unverified_user_cannot_open_or_post_import(): void
    {
        $this->get(route('vocabulary.import'))->assertRedirect(route('login'));
        $this->post(route('vocabulary.import.preview'))->assertRedirect(route('login'));
        $this->post(route('vocabulary.import.confirm'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create());
        $this->get(route('vocabulary.import'))->assertRedirect(route('verification.notice'));
        $this->post(route('vocabulary.import.preview'))->assertRedirect(route('verification.notice'));
        $this->post(route('vocabulary.import.confirm'))->assertRedirect(route('verification.notice'));
    }

    public function test_private_http_journey_previews_without_writes_then_imports_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('vocabulary.import'))->assertOk()->assertSee('Імпорт слів')
            ->assertSee('name="_token"', false)->assertDontSee('Системні теми');

        $this->post(route('vocabulary.import.preview'), [
            'file' => $this->csv("word_en,word_uk,level,lesson,topic\nFriend,друг,A1,1,People\nFriend,друг,A1,2,Other\n"),
        ])->assertRedirect(route('vocabulary.import'));
        $preview = session('import_preview');
        $this->assertSame('private', $preview['scope']);
        $this->assertSame(2, $preview['unique_links']);
        $this->assertSame(0, Topic::count());
        $this->assertSame(0, Word::count());
        $this->assertSame(0, DB::table('topic_word')->count());
        $this->assertSame(session()->getId(), session('vocabulary_import_uploads')[$preview['token']]['session_id']);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('vocabulary.import'))->assertSee('Попередній перегляд');
        $this->assertSame(session()->getId(), session('vocabulary_import_uploads')[$preview['token']]['session_id']);

        $this->post(route('vocabulary.import.confirm'), ['token' => $preview['token'], 'scope' => 'private'])
            ->assertRedirect(route('vocabulary.import'))->assertSessionHas('import_report');
        $this->assertSame(2, Topic::where('user_id', $user->id)->count());
        $this->assertSame(1, Word::count());
        $this->assertSame(2, DB::table('topic_word')->count());
        $this->assertSame(1, session('import_report')['new_words']);
        $this->get(route('vocabulary.import'))->assertSee('Імпорт завершено');

        $this->post(route('vocabulary.import.confirm'), ['token' => $preview['token'], 'scope' => 'private'])
            ->assertSessionHasErrors('file');
        $this->assertSame(2, Topic::count());
    }

    public function test_admin_defaults_to_private_and_system_import_needs_separate_confirmation(): void
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', $admin->email);
        $this->actingAs($admin);
        $this->get(route('vocabulary.import'))->assertOk()->assertSee('Системні теми');

        $this->post(route('vocabulary.import.preview'), [
            'scope' => 'system',
            'file' => $this->csv("word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n"),
        ])->assertRedirect(route('vocabulary.import'));
        $preview = session('import_preview');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('vocabulary.import'))->assertSee('Підтверджую імпорт системних тем');
        $this->post(route('vocabulary.import.confirm'), ['scope' => 'system', 'token' => $preview['token']])
            ->assertSessionHasErrors('system_confirmation');
        $this->assertSame(0, Topic::count());
        $this->get(route('vocabulary.import'))->assertSee('Підтверджую імпорт системних тем');

        $this->post(route('vocabulary.import.confirm'), [
            'scope' => 'system', 'token' => $preview['token'], 'system_confirmation' => '1',
        ])->assertRedirect(route('vocabulary.import'))->assertSessionHas('import_report');
        $this->assertSame(1, Topic::whereNull('user_id')->count());
        $this->assertSame('system', session('import_report')['scope']);
    }

    public function test_scope_tampering_is_forbidden_and_preview_does_not_expose_foreign_topics(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        Topic::factory()->for($other)->create(['name' => 'Private Secret', 'level' => 'A1']);
        $this->actingAs($member);

        $this->post(route('vocabulary.import.preview'), [
            'scope' => 'system',
            'file' => $this->csv("word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n"),
        ])->assertForbidden();
        $this->post(route('vocabulary.import.confirm'), ['scope' => 'system', 'token' => 'fake'])->assertForbidden();
        $this->assertSame(1, Topic::count());
        $this->assertSame(0, Word::count());

        $this->post(route('vocabulary.import.preview'), [
            'scope' => 'private',
            'file' => $this->csv("word_en,word_uk,level,topic\nApple,яблуко,A1,Private Secret\n"),
        ])->assertRedirect();
        $this->assertSame(1, session('import_preview')['new_topics']);
        $this->get(route('vocabulary.import'))->assertDontSee($other->email);
    }

    public function test_invalid_csv_and_changed_or_expired_preview_never_write(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('vocabulary.import.preview'), [])->assertSessionHasErrors('file');
        $this->post(route('vocabulary.import.preview'), [
            'file' => $this->csv("word_en,word_uk,level,topic\nApple,,A1,Food\n"),
        ])->assertSessionHasErrors('file');
        $this->assertStringContainsString('Рядок 2', session('errors')->first('file'));
        $this->post(route('vocabulary.import.preview'), [
            'file' => UploadedFile::fake()->create('large.csv', 1025),
        ])->assertSessionHasErrors('file');

        $this->post(route('vocabulary.import.preview'), [
            'file' => $this->csv("word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n"),
        ])->assertRedirect();
        $token = session('import_preview')['token'];
        $this->withCookie(config('session.cookie'), session()->getId());
        Storage::disk('local')->put('vocabulary-imports/'.$token.'.csv', 'changed');
        $this->post(route('vocabulary.import.confirm'), ['scope' => 'private', 'token' => $token])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, Topic::count());

        $this->freezeTime();
        $this->post(route('vocabulary.import.preview'), [
            'file' => $this->csv("word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n"),
        ])->assertRedirect();
        $token = session('import_preview')['token'];
        $this->travel(30)->minutes();
        $this->post(route('vocabulary.import.confirm'), ['scope' => 'private', 'token' => $token])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, Topic::count());
    }

    public function test_scope_change_and_revoked_system_access_cannot_confirm_preview(): void
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', $admin->email);
        $this->actingAs($admin);
        $this->post(route('vocabulary.import.preview'), [
            'scope' => 'system',
            'file' => $this->csv("word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n"),
        ])->assertRedirect();
        $token = session('import_preview')['token'];
        $this->withCookie(config('session.cookie'), session()->getId());

        $this->post(route('vocabulary.import.confirm'), ['scope' => 'private', 'token' => $token])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, Topic::count());

        $this->post(route('vocabulary.import.preview'), [
            'scope' => 'system',
            'file' => $this->csv("word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n"),
        ])->assertRedirect();
        $token = session('import_preview')['token'];
        config()->set('admin.emails', '');
        $this->post(route('vocabulary.import.confirm'), [
            'scope' => 'system', 'token' => $token, 'system_confirmation' => '1',
        ])->assertForbidden();
        $this->assertSame(0, Topic::count());
    }

    public function test_ambiguous_topics_show_error_without_confirmation_token(): void
    {
        $user = User::factory()->create();
        Topic::factory()->count(2)->for($user)->create(['name' => 'Food', 'level' => 'A1']);
        $this->actingAs($user);
        $this->post(route('vocabulary.import.preview'), [
            'file' => $this->csv("word_en,word_uk,level,topic\nApple,яблуко,A1,Food\n"),
        ])->assertRedirect();
        $this->assertFalse(session('import_preview')['can_import']);
        $this->assertNull(session('import_preview')['token']);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('vocabulary.import'))->assertSee('неоднозначні теми')->assertDontSee('name="token"', false);
        $this->assertSame(2, Topic::count());
        $this->assertSame(0, Word::count());
    }

    public function test_real_csv_completes_http_journey_on_isolated_database(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $file = new UploadedFile(base_path('docs/temp/vocabulary-table-001.csv'), 'sample.csv', 'text/csv', null, true);

        $this->post(route('vocabulary.import.preview'), ['file' => $file])->assertRedirect();
        $preview = session('import_preview');
        $this->assertSame(180, $preview['rows']);
        $this->assertSame(6, $preview['topics']);
        $this->assertSame(179, $preview['unique_pairs']);
        $this->assertSame(0, Topic::count());
        $this->withCookie(config('session.cookie'), session()->getId());

        $this->post(route('vocabulary.import.confirm'), ['scope' => 'private', 'token' => $preview['token']])
            ->assertSessionHas('import_report');
        $this->assertSame(6, Topic::where('user_id', $user->id)->count());
        $this->assertSame(179, Word::count());
        $this->assertSame(180, DB::table('topic_word')->count());
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('words.csv', $content);
    }
}
