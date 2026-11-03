<?php

namespace App\Models;

use App\TextKeyNormalizer;
use Database\Factories\TopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Validator;

#[Fillable(['user_id', 'level', 'name'])]
class Topic extends Model
{
    /** @use HasFactory<TopicFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function words(): BelongsToMany
    {
        return $this->belongsToMany(Word::class);
    }

    /** @param Builder<Topic> $query */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->whereNull('topics.user_id')->orWhere('topics.user_id', $user->id);
        });
    }

    protected static function booted(): void
    {
        static::saving(function (Topic $topic): void {
            Validator::make($topic->getAttributes(), [
                'user_id' => ['nullable', 'integer'],
                'level' => ['required', 'string'],
                'name' => ['required', 'string'],
            ])->validate();

            $topic->level = mb_strtoupper(TextKeyNormalizer::display($topic->level), 'UTF-8');
            $topic->name = TextKeyNormalizer::display($topic->name);

            Validator::make($topic->getAttributes(), [
                'level' => ['required', 'max:20'],
                'name' => ['required', 'max:160'],
            ])->validate();
        });
    }
}
