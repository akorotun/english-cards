<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
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

    public function test_registration_creates_an_unverified_user_and_first_profile_atomically(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'email' => '  ANNA@example.com ',
            'profile_name' => 'Анна',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('anna@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('SecurePassword123!', $user->password));
        $this->assertDatabaseHas('learning_profiles', [
            'user_id' => $user->id,
            'name' => 'Анна',
            'theme_preference' => 'system',
        ]);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_guest_auth_pages_show_the_expected_forms(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Ім’я першого профілю');
        $this->get(route('login'))->assertOk()->assertSee('Забули пароль?');
        $this->get(route('password.request'))->assertOk()->assertSee('Надіслати посилання');
    }

    public function test_registration_rejects_duplicate_email_missing_profile_and_unconfirmed_password(): void
    {
        User::factory()->create(['email' => 'anna@example.com']);

        $this->post(route('register'), [
            'email' => 'ANNA@example.com',
            'profile_name' => '',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['email', 'profile_name', 'password']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('learning_profiles', 0);
        $this->assertGuest();
    }

    public function test_login_and_logout_manage_the_session(): void
    {
        $user = User::factory()->create(['password' => 'SecurePassword123!']);
        $this->get(route('login'))->assertOk();
        $oldSessionId = session()->getId();

        $this->post(route('login'), [
            'email' => strtoupper($user->email),
            'password' => 'SecurePassword123!',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());

        $this->withSession(['active_learning_profile_id' => 123]);
        $oldToken = session()->token();
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertFalse(session()->has('active_learning_profile_id'));
        $this->assertNotSame($oldToken, session()->token());
    }

    public function test_invalid_login_has_a_generic_error_and_is_throttled(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $unknownAccountMessage = session('errors')->first('email');

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $this->assertSame($unknownAccountMessage, session('errors')->first('email'));

        foreach (range(1, 3) as $attempt) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->assertGuest();
    }

    public function test_only_verified_users_can_open_the_home_page(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified);
        $this->get(route('home'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('Підтвердіть електронну пошту');

        $unverified->markEmailAsVerified();
        $this->get(route('home'))->assertOk();
    }

    public function test_signed_verification_link_verifies_only_its_owner(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $otherUser = User::factory()->unverified()->create();
        $this->actingAs($otherUser)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);

        $this->actingAs($user)->get($url.'&invalid=1')->assertForbidden();
        $expiredUrl = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->get($expiredUrl)->assertForbidden();
        $this->actingAs($user)->get($url)->assertRedirect(route('home'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_user_can_resend_verification_email_with_a_rate_limit(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('verification.send'))->assertRedirect();
        }

        Notification::assertSentTo($user, VerifyEmail::class, 5);
        $this->post(route('verification.send'))->assertStatus(429);
    }
}
