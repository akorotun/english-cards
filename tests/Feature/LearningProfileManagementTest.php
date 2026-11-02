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

    public function test_header_shows_the_active_profile_and_updates_after_switching(): void
    {
        $user = User::factory()->create();
        $first = LearningProfile::factory()->for($user)->create(['name' => 'Перший профіль']);
        $second = LearningProfile::factory()->for($user)->create(['name' => 'Другий профіль']);
        $this->actingAs($user)->withSession([ActiveLearningProfile::SESSION_KEY => $first->id]);

        $this->get(route('profiles.index'))->assertOk()
            ->assertSee('aria-label="Активний профіль: Перший профіль"', false)
            ->assertSee('aria-label="Відкрити меню"', false)
            ->assertSee('Імпорт слів');

        $this->postJson(route('profiles.activate', $second))->assertOk()
            ->assertJsonPath('profile_name', 'Другий профіль');
        $this->get(route('profiles.index'))->assertOk()
            ->assertSee('aria-label="Активний профіль: Другий профіль"', false)
            ->assertDontSee('aria-label="Активний профіль: Перший профіль"', false);
    }

    public function test_active_profile_theme_can_be_changed_without_changing_other_profiles(): void
    {
        $user = User::factory()->create();
        $first = LearningProfile::factory()->for($user)->create();
        $second = LearningProfile::factory()->for($user)->create(['theme_preference' => ThemePreference::Dark]);
        $this->actingAs($user)->withSession([ActiveLearningProfile::SESSION_KEY => $first->id]);

        $this->get(route('profiles.show', $first))->assertOk()->assertSee('Вибрати тему оформлення');

        foreach (['light', 'dark', 'system'] as $theme) {
            $this->patchJson(route('profiles.theme.update', $first), ['theme_preference' => $theme])
                ->assertOk()->assertJsonPath('theme_preference', $theme);
            $this->assertSame($theme, $first->fresh()->theme_preference->value);
            $this->assertSame(ThemePreference::Dark, $second->fresh()->theme_preference);
            $this->get(route('home'))->assertOk()->assertSee('data-theme="'.$theme.'"', false);
        }

    }

    public function test_theme_change_rejects_invalid_values_and_inactive_or_foreign_profiles(): void
    {
        $user = User::factory()->create();
        $active = LearningProfile::factory()->for($user)->create();
        $inactive = LearningProfile::factory()->for($user)->create();
        $foreign = LearningProfile::factory()->for(User::factory()->create())->create();
        $this->actingAs($user)->withSession([ActiveLearningProfile::SESSION_KEY => $active->id]);

        $this->patchJson(route('profiles.theme.update', $active), ['theme_preference' => 'purple'])
            ->assertUnprocessable()->assertJsonValidationErrors('theme_preference');
        $this->patchJson(route('profiles.theme.update', $active), [])
            ->assertUnprocessable()->assertJsonValidationErrors('theme_preference');
        $this->patchJson(route('profiles.theme.update', $inactive), ['theme_preference' => 'dark'])->assertNotFound();
        $this->patchJson(route('profiles.theme.update', $foreign), ['theme_preference' => 'dark'])->assertNotFound();

        $this->assertSame(ThemePreference::System, $active->fresh()->theme_preference);
        $this->assertSame(ThemePreference::System, $inactive->fresh()->theme_preference);
        $this->assertSame(ThemePreference::System, $foreign->fresh()->theme_preference);
    }

    public function test_theme_follows_the_active_profile_and_unselected_pages_use_system_default(): void
    {
        $user = User::factory()->create();
        $light = LearningProfile::factory()->for($user)->create(['theme_preference' => ThemePreference::Light]);
        $dark = LearningProfile::factory()->for($user)->create(['theme_preference' => ThemePreference::Dark]);
        $this->actingAs($user);

        $this->get(route('profiles.index'))->assertOk()->assertSee('data-theme="system"', false);
        $this->postJson(route('profiles.activate', $light))->assertOk()
            ->assertJsonPath('theme_preference', 'light');
        $this->get(route('profiles.index'))->assertOk()->assertSee('data-theme="light"', false);
        $this->postJson(route('profiles.activate', $dark))->assertOk()
            ->assertJsonPath('theme_preference', 'dark');
        $this->get(route('home'))->assertOk()->assertSee('data-theme="dark"', false);
        $this->get(route('profiles.show', $light))->assertOk()->assertSee('Щоб змінити тему, спочатку оберіть цей профіль.');
    }

    public function test_theme_update_requires_authentication_and_verified_email(): void
    {
        $user = User::factory()->unverified()->create();
        $profile = LearningProfile::factory()->for($user)->create();

        $this->patch(route('profiles.theme.update', $profile), ['theme_preference' => 'dark'])
            ->assertRedirect(route('login'));
        $this->actingAs($user)->patch(route('profiles.theme.update', $profile), ['theme_preference' => 'dark'])
            ->assertRedirect(route('verification.notice'));
        $this->assertSame(ThemePreference::System, $profile->fresh()->theme_preference);
    }
}
