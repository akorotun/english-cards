<?php

namespace Tests\Feature;

use App\Models\LearningProfile;
use App\Models\ProgressSyncBatch;
use App\Models\Topic;
use App\Models\User;
use App\Models\Word;
use App\Models\WordProgress;
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
        $this->get(route('demo.start', ['mode' => 'check', 'topic_ids' => [$first->id]]))->assertOk()->assertSee('Цей режим демо поки недоступний');
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

    public function test_guest_learning_cards_are_scoped_to_system_topics_and_do_not_change_progress(): void
    {
        $profile = LearningProfile::factory()->create();
        $shared = Word::factory()->create(['word_en' => 'hello', 'word_uk' => 'привіт']);
        $other = Word::factory()->create(['word_en' => 'light', 'word_uk' => 'світло']);
        $system = Topic::factory()->hasAttached($shared)->hasAttached($other)->create(['name' => 'System topic']);
        $second = Topic::factory()->hasAttached($shared)->create();
        $private = Topic::factory()->for($profile->user)->hasAttached($shared)
            ->hasAttached(Word::factory()->create(['word_en' => 'privateword', 'word_uk' => 'таємниця']))
            ->create(['name' => 'Private topic']);
        WordProgress::factory()->for($profile)->for($shared)->create(['knowledge_level' => 3]);
        ProgressSyncBatch::factory()->for($profile)->create();
        $tables = ['users', 'learning_profiles', 'word_progress', 'progress_sync_batches'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
        $params = ['mode' => 'learning', 'topic_ids' => [$system->id, $second->id], 'word_ids' => $private->words->modelKeys(), 'learning_profile_id' => $profile->id];

        $response = $this->get(route('demo.start', $params));
        $response->assertOk()->assertViewIs('learning.cards')->assertSee('data-learning-session', false)
            ->assertSee('hello')->assertSee('data-learning-uk', false)->assertSee('Назад')->assertSee('Далі')->assertSee('Пройти ще раз')
            ->assertSee(route('demo.selection', ['topic_ids' => [$system->id, $second->id]]))
            ->assertDontSee('Private topic')->assertDontSee('privateword')->assertDontSee('таємниця')
            ->assertDontSee('data-check-session', false)->assertDontSee('data-check-sync', false);
        $this->assertSame([$shared->id, $other->id], $response->viewData('words')->modelKeys());
        $this->assertSame(['привіт', 'світло'], $response->viewData('words')->pluck('word_uk')->all());

        $this->get(route('demo.selection', ['topic_ids' => [$system->id, $second->id]]))->assertOk();
        $this->get(route('demo.start', $params))->assertOk();
        $this->getJson(route('demo.start', ['mode' => 'learning', 'topic_ids' => [$system->id, $private->id]]))
            ->assertUnprocessable()->assertDontSee('privateword')->assertDontSee('Private topic');
        foreach ($before as $table => $contents) {
            $this->assertSame($contents, DB::table($table)->get()->toJson(), $table);
        }
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
