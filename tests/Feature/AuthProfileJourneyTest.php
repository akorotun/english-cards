<?php

namespace Tests\Feature;

use App\ActiveLearningProfile;
use App\Models\User;
use App\ThemePreference;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthProfileJourneyTest extends TestCase
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

    public function test_registered_user_can_verify_manage_profiles_and_choose_again_after_login(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'email' => 'anna@example.com',
            'profile_name' => 'Анна',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->sole();
        $firstProfile = $user->learningProfiles()->sole();
        $verification = Notification::sent($user, VerifyEmail::class);
        $this->assertCount(1, $verification);
        $verificationUrl = $verification[0]->toMail($user)->actionUrl;

        $this->get(route('home'))->assertRedirect(route('verification.notice'));
        $this->get($verificationUrl)->assertRedirect(route('home'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get(route('home'))->assertOk()->assertSee('Анна');
        $this->assertSame($firstProfile->id, session(ActiveLearningProfile::SESSION_KEY));

        $this->post(route('profiles.store'), ['name' => 'Дитина'])->assertRedirect();
        $secondProfile = $user->learningProfiles()->where('name', 'Дитина')->sole();
        $this->patch(route('profiles.update', $firstProfile), ['name' => 'Анна оновлена'])
            ->assertRedirect(route('profiles.show', $firstProfile));
        $this->patch(route('profiles.theme.update', $firstProfile), ['theme_preference' => 'dark'])->assertRedirect();
        $this->assertSame('Анна оновлена', $firstProfile->fresh()->name);
        $this->assertSame(ThemePreference::Dark, $firstProfile->fresh()->theme_preference);

        $this->post(route('profiles.activate', $secondProfile))->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk()->assertSee('Дитина');
        $this->assertSame($secondProfile->id, session(ActiveLearningProfile::SESSION_KEY));
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
        $this->assertFalse(session()->has(ActiveLearningProfile::SESSION_KEY));

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'SecurePassword123!',
        ])->assertRedirect(route('home'));
        $this->get(route('home'))->assertRedirect(route('profiles.index'));
        $this->assertFalse(session()->has(ActiveLearningProfile::SESSION_KEY));
        $this->get(route('profiles.index'))->assertOk()->assertSee('Анна оновлена')->assertSee('Дитина');
        $this->post(route('profiles.activate', $firstProfile))->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk()->assertSee('Анна оновлена')
            ->assertSee('data-theme="dark"', false);
    }
}
