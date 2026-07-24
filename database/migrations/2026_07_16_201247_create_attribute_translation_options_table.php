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
        Schema::create('attribute_translation_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_option_id')
                ->constrained('attribute_options')
                ->cascadeOnDelete();

            $table->string('locale', 5);
            $table->string('label');

            $table->timestamps();

            $table->unique(['attribute_option_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_translation_options');
    }
};
