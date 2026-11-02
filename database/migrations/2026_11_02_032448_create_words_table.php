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
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->string('word_en', 191);
            $table->string('word_uk', 191);
            $table->string('word_en_key', 191)->collation('utf8mb4_bin');
            $table->string('word_uk_key', 191)->collation('utf8mb4_bin');
            $table->string('image_path')->nullable();
            $table->text('example_en')->nullable();
            $table->text('example_uk')->nullable();
            $table->timestamps();

            $table->unique(['word_en_key', 'word_uk_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
