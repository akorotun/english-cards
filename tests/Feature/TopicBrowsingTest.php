<?php

namespace Tests\Feature;

use App\ActiveLearningProfile;
use App\Models\LearningProfile;
use App\Models\ProgressSyncBatch;
use App\Models\Topic;
use App\Models\User;
use App\Models\Word;
use App\Models\WordProgress;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TopicBrowsingTest extends TestCase
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

    public function test_guests_and_unverified_users_cannot_browse_topics(): void
    {
        $this->get(route('topics.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('topics.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_only_system_and_owned_topics_and_levels_are_visible(): void
    {
        $user = User::factory()->create();
        $system = Topic::factory()->create(['name' => 'Alpha', 'level' => 'B1']);
        $own = Topic::factory()->for($user)->create(['name' => 'Beta', 'level' => 'A1-A2']);
        $foreign = Topic::factory()->for(User::factory())->create(['name' => 'Secret topic', 'level' => 'SECRET']);
        $foreign->words()->attach(Word::factory()->create(['word_en' => 'hidden', 'word_uk' => 'прихований']));
        $response = $this->actingAs($user)->get(route('topics.index', ['scope' => 'all', 'level' => '']));

        $response->assertOk()->assertViewIs('topics.index')
            ->assertSee('Alpha')->assertSee('Beta')->assertSee('Системна')->assertSee('Приватна')
            ->assertDontSee('Secret topic')->assertDontSee('SECRET')->assertDontSee('прихований')
            ->assertSeeInOrder(['href="'.route('topics.index').'"', 'aria-current="page"', 'Теми</a>'], false)
            ->assertSee(route('vocabulary.import'), false);
        $this->assertSame([$system->id, $own->id], $response->viewData('topics')->modelKeys());
        $this->assertSame(['A1-A2', 'B1'], $response->viewData('levels')->all());
        $this->assertSame(2, $response->viewData('topics')->total());
    }

    public function test_admin_cannot_browse_another_accounts_private_topics(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        config()->set('admin.emails', $admin->email);
        $system = Topic::factory()->create(['name' => 'Alpha']);
        $own = Topic::factory()->for($admin)->create(['name' => 'Beta']);
        Topic::factory()->for(User::factory())->create(['name' => 'Foreign secret', 'level' => 'C2']);
        $response = $this->actingAs($admin)->withSession(['_token' => 'testC2token'])->get(route('topics.index', ['scope' => 'all', 'level' => '']));

        $response->assertOk()->assertDontSee('Foreign secret')->assertDontSee('name="level" value="C2"', false);
        $this->assertSame([$system->id, $own->id], $response->viewData('topics')->modelKeys());
        $this->assertNotContains('C2', $response->viewData('levels')->all());
    }

    public function test_topic_access_does_not_depend_on_the_active_profile(): void
    {
        $user = User::factory()->create();
        $system = Topic::factory()->create(['name' => 'Alpha']);
        $own = Topic::factory()->for($user)->create(['name' => 'Beta']);
        $this->actingAs($user);
        $this->assertSame([$system->id, $own->id], $this->get(route('topics.index', ['scope' => 'all', 'level' => '']))->assertOk()->viewData('topics')->modelKeys());
        $profiles = LearningProfile::factory()->count(2)->for($user)->create();
        $foreignProfile = LearningProfile::factory()->create();

        foreach ([$profiles[0]->id, $profiles[1]->id, $foreignProfile->id, 999999] as $profileId) {
            $response = $this->withSession([ActiveLearningProfile::SESSION_KEY => $profileId])->get(route('topics.index', ['scope' => 'all', 'level' => '']));
            $response->assertOk();
            $this->assertSame([$system->id, $own->id], $response->viewData('topics')->modelKeys());
        }
    }

    public function test_word_counts_use_pivot_links_and_include_empty_topics(): void
    {
        $user = User::factory()->create();
        $first = Topic::factory()->create(['name' => 'Alpha']);
        $second = Topic::factory()->for($user)->create(['name' => 'Beta']);
        $empty = Topic::factory()->create(['name' => 'Empty']);
        $shared = Word::factory()->create();
        $first->words()->attach([$shared->id, Word::factory()->create()->id]);
        $second->words()->attach($shared);
        Word::factory()->create();
        $response = $this->actingAs($user)->get(route('topics.index', ['scope' => 'all', 'level' => '']));

        $response->assertOk()->assertSee('0 слів')->assertSee('1 слово')->assertSee('2 слова');
        $this->assertSame([$first->id => 2, $second->id => 1, $empty->id => 0], $response->viewData('topics')->pluck('words_count', 'id')->all());
    }

    public function test_topics_follow_id_order_across_pages_even_with_duplicate_names(): void
    {
        $user = User::factory()->create();
        $first = Topic::factory()->create(['name' => 'Zebra']);
        $duplicates = Topic::factory()->count(21)->create(['name' => 'Alpha']);
        $this->actingAs($user);
        $firstPage = $this->get(route('topics.index'))->assertOk()->viewData('topics');
        $secondPage = $this->get(route('topics.index', ['page' => 2]))->assertOk()->viewData('topics');

        $this->assertSame(20, $firstPage->perPage());
        $this->assertSame(22, $firstPage->total());
        $this->assertSame([$first->id, ...$duplicates->take(19)->modelKeys()], $firstPage->modelKeys());
        $this->assertSame($duplicates->slice(19)->values()->modelKeys(), $secondPage->modelKeys());
    }

    /** @param list<string> $expectedNames */
    #[DataProvider('filters')]
    public function test_scope_and_level_filters_combine_without_bypassing_visibility(string $scope, string $level, array $expectedNames): void
    {
        $user = User::factory()->create();
        Topic::factory()->create(['name' => 'Alpha', 'level' => 'A1']);
        Topic::factory()->create(['name' => 'Beta', 'level' => 'B1']);
        Topic::factory()->for($user)->create(['name' => 'Gamma', 'level' => 'A1']);
        Topic::factory()->for($user)->create(['name' => 'Omega', 'level' => 'B1']);
        Topic::factory()->for(User::factory())->create(['name' => 'Secret', 'level' => 'A1']);
        $response = $this->actingAs($user)->get(route('topics.index', ['scope' => $scope, 'level' => $level]));

        $response->assertOk()->assertDontSee('Secret');
        $this->assertSame($expectedNames, $response->viewData('topics')->pluck('name')->all());
        $this->assertSame(['A1', 'B1'], $response->viewData('levels')->all());
        $this->assertSame(['scope' => $scope, 'level' => $level], $response->viewData('filters'));
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function filters(): array
    {
        return [
            'all topics' => ['all', '', ['Alpha', 'Beta', 'Gamma', 'Omega']],
            'system topics' => ['system', '', ['Alpha', 'Beta']],
            'private topics' => ['private', '', ['Gamma', 'Omega']],
            'level only' => ['all', 'A1', ['Alpha', 'Gamma']],
            'system and level' => ['system', 'B1', ['Beta']],
            'private and level' => ['private', 'A1', ['Gamma']],
            'unknown level' => ['all', 'UNKNOWN', []],
        ];
    }

    public function test_defaults_and_explicit_all_filters_are_distinct(): void
    {
        $user = User::factory()->create();
        $default = Topic::factory()->create(['name' => 'System default', 'level' => 'A1-A2']);
        $otherLevel = Topic::factory()->create(['name' => 'Other level', 'level' => 'B1']);
        $own = Topic::factory()->for($user)->create(['name' => 'Own topic', 'level' => 'A1-A2']);
        $this->actingAs($user);

        $response = $this->get(route('topics.index'))->assertOk();
        $this->assertSame(['scope' => 'system', 'level' => 'A1-A2'], $response->viewData('filters'));
        $this->assertSame([$default->id], $response->viewData('topics')->modelKeys());
        $all = $this->get(route('topics.index', ['scope' => 'all', 'level' => '']))->assertOk();
        $this->assertSame([$default->id, $otherLevel->id, $own->id], $all->viewData('topics')->modelKeys());
    }

    public function test_search_combines_with_access_filters_and_survives_navigation(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $matches = Topic::factory()->count(21)->for($user)->create(['name' => 'Travel 100% Guide', 'level' => 'B1']);
        Topic::factory()->for($user)->create(['name' => 'Travel 1000 Guide', 'level' => 'B1']);
        Topic::factory()->for($user)->create(['name' => 'Travel 100% Other', 'level' => 'A1']);
        Topic::factory()->for($foreign)->create(['name' => 'Travel 100% Secret', 'level' => 'B1']);
        $this->actingAs($user);
        $parameters = ['scope' => 'private', 'level' => 'B1', 'search' => '100%'];
        $response = $this->get(route('topics.index', $parameters))->assertOk()->assertDontSee('Secret');
        $this->assertSame(21, $response->viewData('topics')->total());
        $this->assertSame($matches->take(20)->modelKeys(), $response->viewData('topics')->modelKeys());
        parse_str(parse_url($response->viewData('topics')->nextPageUrl(), PHP_URL_QUERY), $next);
        $this->assertEquals([...$parameters, 'page' => '2'], $next);
        $pageTwo = $this->get($response->viewData('topics')->nextPageUrl())->assertOk();
        $this->assertSame([$matches->last()->id], $pageTwo->viewData('topics')->modelKeys());
        $detailUrl = route('topics.show', ['topic' => $matches->last()->id, ...$parameters, 'topics_page' => 2]);
        $pageTwo->assertSee($detailUrl);
        $detail = $this->get($detailUrl)->assertOk();
        parse_str(parse_url($detail->viewData('backUrl'), PHP_URL_QUERY), $backParameters);
        $this->assertEquals([...$parameters, 'page' => '2'], $backParameters);
        $this->getJson(route('topics.index', ['search' => ['bad']]))->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_pagination_keeps_filters_and_drops_untrusted_query_parameters(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $matching = Topic::factory()->count(21)->for($user)->create(['name' => 'Alpha', 'level' => 'A1']);
        Topic::factory()->create(['name' => 'Hidden by type', 'level' => 'A1']);
        Topic::factory()->for($user)->create(['name' => 'Hidden by level', 'level' => 'B1']);
        Topic::factory()->for($foreign)->create(['name' => 'Foreign topic', 'level' => 'A1']);
        $parameters = ['scope' => 'private', 'level' => 'A1', 'user_id' => $foreign->id, 'is_admin' => '1'];
        $response = $this->actingAs($user)->get(route('topics.index', $parameters));
        $response->assertOk()->assertSee('Наступна');
        $paginator = $response->viewData('topics');

        $this->assertSame(21, $paginator->total());
        parse_str(parse_url($paginator->nextPageUrl(), PHP_URL_QUERY), $nextPageParameters);
        $this->assertEquals(['scope' => 'private', 'level' => 'A1', 'page' => '2'], $nextPageParameters);
        $nextPage = $this->get($paginator->nextPageUrl())->assertOk();
        $this->assertSame([$matching->last()->id], $nextPage->viewData('topics')->modelKeys());
        $nextPage->assertSee('Попередня')->assertDontSee('Foreign topic');
    }

    public function test_filter_menus_preserve_selected_values_in_the_get_form(): void
    {
        $user = User::factory()->create();
        Topic::factory()->create(['level' => 'B1']);
        Topic::factory()->for($user)->create(['level' => 'A1']);
        $this->actingAs($user);

        foreach ([['scope' => 'all', 'level' => ''], ['scope' => 'system', 'level' => 'B1'], ['scope' => 'private', 'level' => 'A1']] as $filters) {
            $response = $this->get(route('topics.index', $filters))->assertOk();
            $document = new DOMDocument;
            $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $selectedValues = [];

            foreach ($xpath->query('//form[@method="get"]//input[@type="radio"][@checked]') as $input) {
                $selectedValues[$input->getAttribute('name')] = $input->getAttribute('value');
            }

            $this->assertSame($filters, $selectedValues);
            $this->assertSame(2, $xpath->query('//details[@data-filter-menu]/summary[@aria-labelledby]')->length);
            $this->get(route('topics.index', $selectedValues))->assertOk()->assertViewHas('filters', $filters);
        }
    }

    public function test_owner_and_profile_parameters_cannot_change_visibility(): void
    {
        $user = User::factory()->create();
        $own = Topic::factory()->for($user)->create();
        $foreign = User::factory()->create();
        Topic::factory()->for($foreign)->create(['name' => 'Secret', 'level' => 'FOREIGN']);
        $response = $this->actingAs($user)->get(route('topics.index', [
            'scope' => 'private', 'user_id' => $foreign->id, 'learning_profile_id' => 999, 'is_admin' => true,
        ]));

        $response->assertOk()->assertDontSee('Secret')->assertDontSee('FOREIGN');
        $this->assertSame([$own->id], $response->viewData('topics')->modelKeys());
    }

    /** @param array<string, mixed> $parameters */
    #[DataProvider('invalidParameters')]
    public function test_invalid_filters_and_pages_are_rejected(array $parameters, string $error): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('topics.index', $parameters))
            ->assertUnprocessable()->assertJsonValidationErrors($error);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidParameters(): array
    {
        return [
            'unknown scope' => [['scope' => 'foreign'], 'scope'],
            'scope array' => [['scope' => ['system']], 'scope'],
            'level array' => [['level' => ['A1']], 'level'],
            'long level' => [['level' => str_repeat('A', 21)], 'level'],
            'zero page' => [['page' => 0], 'page'],
            'negative page' => [['page' => -1], 'page'],
            'fractional page' => [['page' => '1.5'], 'page'],
            'page array' => [['page' => [1]], 'page'],
        ];
    }

    public function test_empty_and_filtered_empty_states_and_small_lists_render(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('topics.index'))->assertOk()
            ->assertSee('Доступних тем поки немає')->assertDontSee('Сторінка 1 з');
        Topic::factory()->create(['level' => 'B1']);
        $this->get(route('topics.index', ['scope' => 'private', 'level' => 'A1']))->assertOk()
            ->assertSee('За вибраними фільтрами тем не знайдено');
        $this->get(route('topics.index', ['page' => 2]))->assertOk()->assertSee('На цій сторінці немає тем');
        $this->get(route('topics.index'))->assertOk()->assertDontSee('Сторінка 1 з');
    }

    public function test_topic_links_keep_list_context_and_escape_user_content(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->for($user)->create(['name' => '<script>alert(1)</script>', 'level' => 'A1']);
        $response = $this->actingAs($user)->get(route('topics.index', ['scope' => 'private', 'level' => 'A1']));

        $response->assertOk()->assertSee($topic->name)->assertDontSee($topic->name, false)
            ->assertSee(route('topics.show', ['topic' => $topic->id, 'scope' => 'private', 'level' => 'A1', 'topics_page' => 1]));
    }

    public function test_counting_words_does_not_issue_queries_per_topic(): void
    {
        $user = User::factory()->create();
        Topic::factory()->count(20)->hasAttached(Word::factory()->count(2))->create();
        $this->actingAs($user);
        DB::enableQueryLog();

        try {
            $response = $this->get(route('topics.index'))->assertOk();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertCount(3, $queries);
        $this->assertCount(20, $response->viewData('topics')->items());
        $this->assertSame([2], $response->viewData('topics')->pluck('words_count')->unique()->values()->all());
    }

    public function test_browsing_leaves_dictionary_and_profile_data_unchanged(): void
    {
        $user = User::factory()->create();
        $profile = LearningProfile::factory()->for($user)->create(['theme_preference' => 'dark']);
        $word = Word::factory()->create();
        $topic = Topic::factory()->for($user)->hasAttached($word)->create();
        WordProgress::factory()->for($profile)->for($word)->create(['knowledge_level' => 2]);
        ProgressSyncBatch::factory()->for($profile)->create();
        $tables = ['users', 'learning_profiles', 'topics', 'words', 'topic_word', 'word_progress', 'progress_sync_batches'];
        $before = [];

        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }

        $this->actingAs($user)->withSession([ActiveLearningProfile::SESSION_KEY => $profile->id]);
        $this->get(route('topics.index'))->assertOk()->assertSee('data-theme="dark"', false);
        $this->get(route('topics.index', ['scope' => 'private', 'level' => $topic->level]))->assertOk();

        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->get()->toJson(), $table.' changed during browsing');
        }
    }
}
