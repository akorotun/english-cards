<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class PublicHomeTest extends TestCase
{
    public function test_guests_see_registration_and_login_links(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeText('Вивчайте англійські слова легко')
            ->assertSeeText('Два режими для практики')
            ->assertSee('href="'.route('register').'">Зареєструватися</a>', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('demo.index').'"', false)
            ->assertSee('data-guest-theme', false)
            ->assertSee('english-cards-guest-theme', false)
            ->assertSeeText('Зареєструватися')
            ->assertSeeText('Увійти')
            ->assertDontSeeText('Мій кабінет');
    }

    public function test_authenticated_users_see_their_account_link_instead_of_guest_actions(): void
    {
        $user = User::factory()->make();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('href="'.route('home').'">Мій кабінет</a>', false)
            ->assertSeeText('Мій кабінет')
            ->assertDontSeeText('Зареєструватися')
            ->assertDontSeeText('Увійти')
            ->assertDontSee('data-guest-theme', false)
            ->assertDontSee('english-cards-guest-theme', false);
    }

    public function test_guest_theme_is_restored_on_other_guest_pages_without_a_second_switcher(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('english-cards-guest-theme', false)
            ->assertDontSee('data-guest-theme', false);
    }
}
