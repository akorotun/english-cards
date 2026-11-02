<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
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

    public function test_forgot_password_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $existingResponse = $this->post(route('password.email'), ['email' => $user->email]);
        $existingStatus = session('status');
        $unknownResponse = $this->post(route('password.email'), ['email' => 'missing@example.com']);

        $existingResponse->assertRedirect()->assertSessionHas('status');
        $unknownResponse->assertRedirect()->assertSessionHas('status');
        $this->assertSame($existingStatus, session('status'));
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_page_handles_an_untrusted_email_query(): void
    {
        $this->get(route('password.reset', ['token' => 'example', 'email' => 'person@example.com']))
            ->assertOk()->assertSee('person@example.com');

        $this->get(route('password.reset', ['token' => 'example', 'email' => ['bad']]))
            ->assertOk();
    }

    public function test_valid_reset_token_changes_password_and_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $oldRememberToken = $user->remember_token;
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ChangedPassword123!',
            'password_confirmation' => 'ChangedPassword123!',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('ChangedPassword123!', $user->password));
        $this->assertNotSame($oldRememberToken, $user->remember_token);
        $this->assertFalse(Password::tokenExists($user, $token));

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'AnotherPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('ChangedPassword123!', $user->fresh()->password));
    }

    public function test_expired_or_invalid_token_does_not_change_password(): void
    {
        $user = User::factory()->create();
        $oldPassword = $user->password;
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => 'invalid',
            'email' => $user->email,
            'password' => 'ChangedPassword123!',
            'password_confirmation' => 'ChangedPassword123!',
        ])->assertSessionHasErrors('email');

        $this->travel(61)->minutes();
        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ChangedPassword123!',
            'password_confirmation' => 'ChangedPassword123!',
        ])->assertSessionHasErrors('email');

        $this->assertSame($oldPassword, $user->fresh()->password);
    }

    public function test_forgot_password_requests_are_throttled(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.email'), ['email' => 'missing@example.com'])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'missing@example.com'])->assertStatus(429);
    }
}
