<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoSelectionTest extends TestCase
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

    public function test_guest_sees_only_system_topics_and_can_form_a_deduplicated_collection(): void
    {
        $shared = Word::factory()->create();
        $first = Topic::factory()->hasAttached($shared)->create(['name' => 'Public topic']);
        $second = Topic::factory()->hasAttached($shared)->create();
        $secret = Topic::factory()->for(User::factory())->hasAttached(Word::factory()->create(['word_en' => 'secretword']))->create(['name' => 'Secret topic']);
        $before = collect(['users', 'learning_profiles', 'word_progress', 'progress_sync_batches'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();

        $this->get('/')->assertOk()->assertSee('Спробувати без реєстрації')->assertSee(route('register'))->assertSee(route('login'));
        $this->get(route('demo.index'))->assertOk()->assertSee('Public topic')->assertDontSee('Secret topic')
            ->assertDontSee('Тип теми')->assertDontSee('Не показувати вивчені слова')->assertDontSee('Імпорт слів')
            ->assertSee('Прогрес не зберігається');
        $response = $this->get(route('demo.selection', ['topic_ids' => [$first->id, $second->id], 'word_ids' => $secret->words->modelKeys()]));
        $response->assertOk()->assertSee('Почати навчання')->assertSee('Почати перевірку')->assertDontSee('secretword')->assertDontSee('Secret topic');
        $this->assertSame([$shared->id], $response->viewData('words')->modelKeys());
        foreach (['learning', 'check'] as $mode) {
            $this->get(route('demo.start', ['mode' => $mode, 'topic_ids' => [$first->id]]))->assertOk()->assertSee('Цей режим демо поки недоступний');
        }
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
    }

    public function test_invalid_private_missing_and_mixed_topics_are_rejected_without_disclosure(): void
    {
        $public = Topic::factory()->hasAttached(Word::factory())->create();
        $private = Topic::factory()->for(User::factory())->hasAttached(Word::factory()->create(['word_en' => 'secretword']))->create(['name' => 'Secret topic']);
        foreach ([[], [$private->id], [999999], [$public->id, $private->id], [$public->id, 999999], [$public->id, $public->id]] as $ids) {
            foreach ([['demo.selection', null], ['demo.start', 'learning'], ['demo.start', 'check']] as [$route, $mode]) {
                $params = ['topic_ids' => $ids] + ($mode ? ['mode' => $mode] : []);
                $this->getJson(route($route, $params))->assertUnprocessable()->assertDontSee('Secret topic')->assertDontSee('secretword');
            }
        }
        $this->get(route('demo.selection'))->assertRedirect(route('demo.index'))->assertSessionHasErrors('topic_ids');

        $public->update(['user_id' => $private->user_id]);
        $this->getJson(route('demo.start', ['mode' => 'learning', 'topic_ids' => [$public->id]]))->assertUnprocessable();
    }

    public function test_empty_collections_and_protected_actions(): void
    {
        $this->get(route('demo.index'))->assertOk()->assertSee('Системних тем поки немає');
        $empty = Topic::factory()->create();
        foreach (['demo.selection', 'demo.start'] as $route) {
            $params = ['topic_ids' => [$empty->id]] + ($route === 'demo.start' ? ['mode' => 'learning'] : []);
            $this->get(route($route, $params))->assertOk()->assertSee('У вибраних темах поки немає слів')->assertDontSee('Почати навчання');
        }
        foreach (['topics.index', 'learning.index', 'check.index', 'profiles.index', 'vocabulary.import'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->get(route('topics.show', $empty))->assertRedirect(route('login'));
        $this->postJson(route('check.sync'), [])->assertUnauthorized();
    }
}
