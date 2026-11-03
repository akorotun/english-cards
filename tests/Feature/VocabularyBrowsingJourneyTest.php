<?php

namespace Tests\Feature;

use App\Models\LearningProfile;
use App\Models\Topic;
use App\Models\User;
use App\Models\Word;
use App\Models\WordProgress;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VocabularyBrowsingJourneyTest extends TestCase
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

    #[DataProvider('themes')]
    public function test_login_filter_vocabulary_pagination_and_return_preserve_context_and_data(string $theme): void
    {
        $user = User::factory()->create(['password' => 'SecurePassword123!']);
        $profile = LearningProfile::factory()->for($user)->create(['theme_preference' => $theme]);
        Topic::factory()->count(20)->for($user)->create(['name' => 'Earlier topic', 'level' => 'A1']);
        $topic = Topic::factory()->for($user)->create(['name' => str_repeat('Т', 160), 'level' => 'A1']);
        $longWord = Word::factory()->create(['word_en' => str_repeat('a', 191), 'word_uk' => str_repeat('я', 191)]);
        $otherWords = Word::factory()->count(30)->sequence(fn (Sequence $sequence): array => [
            'word_en' => sprintf('word-%02d', $sequence->index),
        ])->create();
        $topic->words()->attach([$longWord->id, ...$otherWords->modelKeys()]);
        $system = Topic::factory()->hasAttached($longWord)->create(['name' => 'System topic', 'level' => 'A1']);
        Topic::factory()->for($user)->create(['name' => 'Other level', 'level' => 'B1']);
        $foreign = Topic::factory()->for(User::factory())->hasAttached($longWord)
            ->create(['name' => 'Foreign secret', 'level' => 'SECRET']);
        WordProgress::factory()->for($profile)->for($longWord)->create(['knowledge_level' => 3]);

        $this->get(route('login'))->assertOk();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'SecurePassword123!'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('home'))->assertOk()->assertSee('data-theme="'.$theme.'"', false);

        $tables = ['users', 'learning_profiles', 'topics', 'words', 'topic_word', 'word_progress', 'progress_sync_batches'];
        $before = [];

        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }

        $list = $this->get(route('topics.index'))->assertOk()->assertSee('data-theme="'.$theme.'"', false)
            ->assertDontSee('Foreign secret')->assertDontSee('SECRET');
        $this->assertSame(23, $list->viewData('topics')->total());

        $filtered = $this->get(route('topics.index', ['scope' => 'private', 'level' => 'A1']))->assertOk();
        $this->assertSame(21, $filtered->viewData('topics')->total());
        $secondListPageUrl = $this->href($filtered, '//a[@rel="next"]');
        $secondListPage = $this->get($secondListPageUrl)->assertOk()->assertSee($topic->name)->assertSee('31 слово');
        $this->assertSame([$topic->id], $secondListPage->viewData('topics')->modelKeys());

        $topicUrl = $this->href($secondListPage, '//a[starts-with(@aria-label,"Переглянути тему ")]');
        $vocabulary = $this->get($topicUrl)->assertOk()->assertSee('Приватна')
            ->assertSee($longWord->word_en)->assertSee($longWord->word_uk)->assertSee('data-theme="'.$theme.'"', false);
        $this->assertSame(31, $vocabulary->viewData('topic')->words_count);
        $this->assertCount(30, $vocabulary->viewData('words')->items());

        $secondWordPage = $this->get($this->href($vocabulary, '//a[@rel="next"]'))->assertOk();
        $this->assertSame([$otherWords->last()->id], $secondWordPage->viewData('words')->modelKeys());
        $this->assertSame(31, $secondWordPage->viewData('topic')->words_count);
        $backUrl = $this->href($secondWordPage, '//a[normalize-space(.)="Назад до тем"]');
        $this->assertSame($secondListPageUrl, $backUrl);
        $returned = $this->get($backUrl)->assertOk()->assertSee('data-theme="'.$theme.'"', false);
        $this->assertSame(2, $returned->viewData('topics')->currentPage());
        $this->assertSame(['scope' => 'private', 'level' => 'A1'], $returned->viewData('filters'));
        $this->assertSame([$topic->id], $returned->viewData('topics')->modelKeys());

        $systemVocabulary = $this->get(route('topics.show', $system))->assertOk()->assertSee('Системна');
        $this->assertSame(1, $systemVocabulary->viewData('topic')->words_count);
        $this->get(route('topics.show', $foreign))->assertNotFound()->assertDontSee('Foreign secret');
        $this->get($this->href($returned, '//nav[@aria-label="Основна навігація"]//a[normalize-space(.)="Імпорт слів"]'))
            ->assertOk()->assertSee(route('topics.index'), false)->assertSee('data-theme="'.$theme.'"', false);

        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->get()->toJson(), $table.' changed during browsing');
        }
    }

    /** @return array<string, array{string}> */
    public static function themes(): array
    {
        return ['system theme' => ['system'], 'light theme' => ['light'], 'dark theme' => ['dark']];
    }

    private function href(TestResponse $response, string $query): string
    {
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $link = (new DOMXPath($document))->query($query)->item(0);
        $this->assertInstanceOf(DOMElement::class, $link);

        return $link->getAttribute('href');
    }
}
