<?php

namespace Tests\Feature;

use App\ActiveLearningProfile;
use App\Models\LearningProfile;
use App\Models\User;
use App\ThemePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProfileManagementTest extends TestCase
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

    public function test_empty_account_can_create_a_first_profile_that_becomes_active(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('home'))->assertRedirect(route('profiles.index'));
        $this->get(route('profiles.index'))->assertOk()->assertSee('У вас поки немає навчальних профілів');

        $this->post(route('profiles.store'), [
            'name' => 'Анна',
            'user_id' => User::factory()->create()->id,
            'theme_preference' => 'dark',
        ])->assertRedirect();

        $profile = $user->learningProfiles()->sole();
        $this->assertSame('Анна', $profile->name);
        $this->assertSame(ThemePreference::System, $profile->theme_preference);
        $this->assertSame($profile->id, session(ActiveLearningProfile::SESSION_KEY));
        $this->get(route('home'))->assertOk()->assertSee('Анна')->assertSee('Перемкнути профіль');
    }

    public function test_one_profile_is_automatically_selected_and_multiple_profiles_require_a_choice(): void
    {
        $user = User::factory()->create();
        $first = LearningProfile::factory()->for($user)->create(['name' => 'Спільна назва']);
        $this->actingAs($user);

        $this->get(route('home'))->assertOk()->assertSee('Спільна назва');
        $this->assertSame($first->id, session(ActiveLearningProfile::SESSION_KEY));

        session()->forget(ActiveLearningProfile::SESSION_KEY);
        $second = LearningProfile::factory()->for($user)->create(['name' => 'Спільна назва']);

        $this->get(route('home'))->assertRedirect(route('profiles.index'));
        $this->get(route('profiles.index'))->assertOk()->assertSee('Спільна назва', false);
        $this->post(route('profiles.activate', $second))->assertRedirect(route('home'));
        $this->assertSame($second->id, session(ActiveLearningProfile::SESSION_KEY));
        $this->post(route('profiles.activate', $first))->assertRedirect(route('home'));
        $this->assertSame($first->id, session(ActiveLearningProfile::SESSION_KEY));

        $this->post(route('profiles.store'), ['name' => 'Спільна назва'])->assertRedirect();
        $this->assertSame(3, $user->learningProfiles()->where('name', 'Спільна назва')->count());
    }

    public function test_profile_settings_can_be_read_and_renamed_without_changing_its_theme(): void
    {
        $user = User::factory()->create();
        $profile = LearningProfile::factory()->for($user)->create([
            'name' => 'Старий профіль',
            'theme_preference' => ThemePreference::Dark,
        ]);
        $this->actingAs($user);

        $this->get(route('profiles.show', $profile))->assertOk()->assertSee('Старий профіль')->assertSee('Темна');
        $this->patch(route('profiles.update', $profile), [
            'name' => 'Новий профіль',
            'theme_preference' => 'light',
        ])->assertRedirect(route('profiles.show', $profile));

        $this->assertSame('Новий профіль', $profile->fresh()->name);
        $this->assertSame(ThemePreference::Dark, $profile->fresh()->theme_preference);
    }

    public function test_profile_names_are_validated_for_creation_and_renaming(): void
    {
        $user = User::factory()->create();
        $profile = LearningProfile::factory()->for($user)->create(['name' => 'Початкова назва']);
        $this->actingAs($user);

        $this->post(route('profiles.store'), ['name' => ' '])->assertSessionHasErrors('name');
        $this->post(route('profiles.store'), ['name' => str_repeat('a', 101)])->assertSessionHasErrors('name');
        $this->patch(route('profiles.update', $profile), ['name' => ' '])->assertSessionHasErrors('name');
        $this->patch(route('profiles.update', $profile), ['name' => str_repeat('a', 101)])->assertSessionHasErrors('name');

        $this->assertSame(1, $user->learningProfiles()->count());
        $this->assertSame('Початкова назва', $profile->fresh()->name);
    }

    public function test_guests_and_unverified_users_cannot_access_profile_routes(): void
    {
        $user = User::factory()->unverified()->create();
        $profile = LearningProfile::factory()->for($user)->create();

        $this->get(route('profiles.index'))->assertRedirect(route('login'));
        $this->get(route('profiles.show', $profile))->assertRedirect(route('login'));
        $this->post(route('profiles.store'), ['name' => 'Зайвий'])->assertRedirect(route('login'));
        $this->patch(route('profiles.update', $profile), ['name' => 'Зайвий'])->assertRedirect(route('login'));
        $this->post(route('profiles.activate', $profile))->assertRedirect(route('login'));

        $this->actingAs($user);
        $this->get(route('profiles.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('profiles.show', $profile))->assertRedirect(route('verification.notice'));
        $this->post(route('profiles.store'), ['name' => 'Зайвий'])->assertRedirect(route('verification.notice'));
        $this->patch(route('profiles.update', $profile), ['name' => 'Зайвий'])->assertRedirect(route('verification.notice'));
        $this->post(route('profiles.activate', $profile))->assertRedirect(route('verification.notice'));

        $this->assertSame(1, $user->learningProfiles()->count());
        $this->assertFalse(session()->has(ActiveLearningProfile::SESSION_KEY));
    }

    public function test_an_account_cannot_view_rename_or_activate_another_accounts_profile(): void
    {
        $user = User::factory()->create();
        $own = LearningProfile::factory()->for($user)->create(['name' => 'Мій профіль']);
        $foreign = LearningProfile::factory()->for(User::factory()->create())->create(['name' => 'Чужий профіль']);
        $this->actingAs($user)->withSession([ActiveLearningProfile::SESSION_KEY => $own->id]);

        $this->get(route('profiles.index'))->assertOk()->assertSee('Мій профіль')->assertDontSee('Чужий профіль');
        $this->get(route('profiles.show', $foreign))->assertNotFound();
        $this->patch(route('profiles.update', $foreign), ['name' => 'Підмінено'])->assertNotFound();
        $this->post(route('profiles.activate', $foreign))->assertNotFound();

        $this->assertSame('Чужий профіль', $foreign->fresh()->name);
        $this->assertSame($own->id, session(ActiveLearningProfile::SESSION_KEY));
    }

    public function test_stale_foreign_and_invalid_session_selections_are_cleared(): void
    {
        $user = User::factory()->create();
        $own = LearningProfile::factory()->for($user)->create();
        $foreign = LearningProfile::factory()->for(User::factory()->create())->create();
        $this->actingAs($user);

        $this->withSession([ActiveLearningProfile::SESSION_KEY => $foreign->id]);
        $this->get(route('profiles.index'))->assertOk();
        $this->assertFalse(session()->has(ActiveLearningProfile::SESSION_KEY));

        $this->withSession([ActiveLearningProfile::SESSION_KEY => ['invalid']]);
        $this->get(route('home'))->assertRedirect(route('profiles.index'));
        $this->assertFalse(session()->has(ActiveLearningProfile::SESSION_KEY));

        $this->withSession([ActiveLearningProfile::SESSION_KEY => $own->id]);
        $own->delete();
        $this->get(route('home'))->assertRedirect(route('profiles.index'));
        $this->assertFalse(session()->has(ActiveLearningProfile::SESSION_KEY));
    }

    public function test_profile_selection_is_isolated_between_users(): void
    {
        $firstUser = User::factory()->create();
        $firstProfile = LearningProfile::factory()->for($firstUser)->create(['name' => 'Перший']);
        $secondUser = User::factory()->create();
        $secondProfile = LearningProfile::factory()->for($secondUser)->create(['name' => 'Другий']);

        $this->actingAs($firstUser)->withSession([ActiveLearningProfile::SESSION_KEY => $firstProfile->id]);
        $this->get(route('home'))->assertOk()->assertSee('Перший');

        $this->actingAs($secondUser);
        $this->get(route('home'))->assertRedirect(route('profiles.index'));
        $this->assertFalse(session()->has(ActiveLearningProfile::SESSION_KEY));
        $this->get(route('home'))->assertOk()->assertSee('Другий');
        $this->assertSame($secondProfile->id, session(ActiveLearningProfile::SESSION_KEY));
    }
}
