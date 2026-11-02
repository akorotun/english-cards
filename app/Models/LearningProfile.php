<?php

namespace App\Models;

use App\ThemePreference;
use Database\Factories\LearningProfileFactory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

#[Fillable(['user_id', 'name', 'theme_preference'])]
class LearningProfile extends Model
{
    /** @use HasFactory<LearningProfileFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    public static function themePreferenceRules(): array
    {
        return ['required', Rule::enum(ThemePreference::class)];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['theme_preference' => ThemePreference::class];
    }
}
