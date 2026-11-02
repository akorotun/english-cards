<?php

namespace App\Models;

use Database\Factories\WordProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['learning_profile_id', 'word_id', 'knowledge_level'])]
class WordProgress extends Model
{
    /** @use HasFactory<WordProgressFactory> */
    use HasFactory;

    protected $table = 'word_progress';

    public function learningProfile(): BelongsTo
    {
        return $this->belongsTo(LearningProfile::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['knowledge_level' => 'integer'];
    }
}
