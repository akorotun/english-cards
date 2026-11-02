<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('progress_sync_batches', function (Blueprint $table) {
            $table->char('session_uuid', 36)->primary();
            $table->foreignId('learning_profile_id')->constrained()->cascadeOnDelete();
            $table->char('payload_hash', 64);
            $table->json('result_json')->nullable();
            $table->timestamp('processed_at');

            $table->index(['learning_profile_id', 'processed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_sync_batches');
    }
};
