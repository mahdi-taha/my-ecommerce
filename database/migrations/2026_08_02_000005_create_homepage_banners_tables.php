<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_banners', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 30);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['placement', 'is_active', 'sort_order', 'id']);
        });
        Schema::create('homepage_banner_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homepage_banner_id')->constrained('homepage_banners')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('button_label')->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->string('image_alt')->nullable();
            $table->timestamps();
            $table->unique(['homepage_banner_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_banner_translations');
        Schema::dropIfExists('homepage_banners');
    }
};
