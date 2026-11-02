<?php

namespace App\Models;

use App\TextKeyNormalizer;
use Database\Factories\WordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;

#[Fillable(['word_en', 'word_uk', 'image_path', 'example_en', 'example_uk'])]
class Word extends Model
{
    /** @use HasFactory<WordFactory> */
    use HasFactory;

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(WordProgress::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Word $word): void {
            Validator::make($word->getAttributes(), [
                'word_en' => ['required', 'string'],
                'word_uk' => ['required', 'string'],
                'image_path' => ['nullable', 'string', 'max:255'],
                'example_en' => ['nullable', 'string'],
                'example_uk' => ['nullable', 'string'],
            ])->validate();

            $word->word_en = TextKeyNormalizer::display($word->word_en);
            $word->word_uk = TextKeyNormalizer::display($word->word_uk);
            $word->word_en_key = TextKeyNormalizer::key($word->word_en);
            $word->word_uk_key = TextKeyNormalizer::key($word->word_uk);

            Validator::make($word->getAttributes(), [
                'word_en' => ['required', 'max:191'],
                'word_uk' => ['required', 'max:191'],
                'word_en_key' => ['required', 'max:191'],
                'word_uk_key' => ['required', 'max:191'],
            ])->validate();
        });
    }
}
