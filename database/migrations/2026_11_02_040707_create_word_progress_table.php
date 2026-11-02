<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('word_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('word_id')->index()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('knowledge_level')->default(0);
            $table->timestamps();

            $table->unique(['learning_profile_id', 'word_id']);
        });

        DB::statement('ALTER TABLE `word_progress` ADD CONSTRAINT `word_progress_knowledge_level_check` CHECK (`knowledge_level` BETWEEN 0 AND 3)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_progress');
    }
};
