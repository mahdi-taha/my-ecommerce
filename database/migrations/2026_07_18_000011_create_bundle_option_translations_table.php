<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_option_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_option_id')
                ->constrained('bundle_options')
                ->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->timestamps();

            $table->unique(['bundle_option_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_option_translations');
    }
};
