<?php

namespace Tests\Feature;

use App\Models\LearningProfile;
use App\Models\User;
use App\ThemePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LearningProfileTest extends TestCase
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

    public function test_users_and_learning_profiles_have_the_expected_schema(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'name'));
        $this->assertTrue(Schema::hasColumns('users', ['id', 'email', 'password', 'email_verified_at', 'remember_token', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('learning_profiles', ['id', 'user_id', 'name', 'theme_preference', 'created_at', 'updated_at']));

        $column = DB::selectOne("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'learning_profiles' AND COLUMN_NAME = 'theme_preference'");

        $this->assertSame('varchar', $column->DATA_TYPE);
        $this->assertSame(10, $column->CHARACTER_MAXIMUM_LENGTH);
        $this->assertSame('NO', $column->IS_NULLABLE);
        $this->assertSame('system', $column->COLUMN_DEFAULT);
    }

    public function test_a_user_can_have_multiple_profiles_and_deleting_the_user_cascades(): void
    {
        $user = User::factory()->create();
        $first = LearningProfile::factory()->for($user)->create(['name' => 'Anna']);
        $second = LearningProfile::factory()->for($user)->create(['name' => 'Bohdan']);

        $this->assertCount(2, $user->learningProfiles);
        $this->assertTrue($first->user->is($user));
        $this->assertTrue($second->user->is($user));

        $user->delete();

        $this->assertDatabaseMissing('learning_profiles', ['id' => $first->id]);
        $this->assertDatabaseMissing('learning_profiles', ['id' => $second->id]);
    }

    public function test_theme_preference_defaults_to_system_and_is_independent_per_profile(): void
    {
        $user = User::factory()->create();
        $first = LearningProfile::factory()->for($user)->create();
        $second = LearningProfile::factory()->for($user)->create(['theme_preference' => ThemePreference::Light]);
        $third = LearningProfile::factory()->for($user)->create(['theme_preference' => ThemePreference::Dark]);

        $this->assertSame(ThemePreference::System, $first->refresh()->theme_preference);
        $this->assertSame(ThemePreference::Light, $second->refresh()->theme_preference);
        $this->assertSame(ThemePreference::Dark, $third->refresh()->theme_preference);

        $first->update(['theme_preference' => ThemePreference::Dark]);

        $this->assertSame(ThemePreference::Dark, $first->refresh()->theme_preference);
        $this->assertSame(ThemePreference::Light, $second->refresh()->theme_preference);
        $this->assertSame(ThemePreference::Dark, $third->refresh()->theme_preference);
    }

    public function test_theme_preference_validation_rejects_unsupported_values(): void
    {
        $rules = ['theme_preference' => LearningProfile::themePreferenceRules()];

        foreach (ThemePreference::cases() as $preference) {
            $this->assertTrue(Validator::make(['theme_preference' => $preference->value], $rules)->passes());
        }

        $this->assertTrue(Validator::make(['theme_preference' => 'blue'], $rules)->fails());
        $this->assertTrue(Validator::make(['theme_preference' => null], $rules)->fails());
    }
}
