<?php

namespace App\Models;

use Database\Factories\ProgressSyncBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['session_uuid', 'learning_profile_id', 'payload_hash', 'result_json', 'processed_at'])]
class ProgressSyncBatch extends Model
{
    /** @use HasFactory<ProgressSyncBatchFactory> */
    use HasFactory;

    protected $primaryKey = 'session_uuid';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    public function learningProfile(): BelongsTo
    {
        return $this->belongsTo(LearningProfile::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result_json' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
