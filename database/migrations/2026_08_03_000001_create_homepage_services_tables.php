<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_service_locks', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('homepage_service_locks')->insert(['id' => 1]);

        Schema::create('homepage_services', function (Blueprint $table) {
            $table->id();
            $table->string('icon', 50);
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order', 'id']);
        });

        Schema::create('homepage_service_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homepage_service_id')->constrained('homepage_services')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 120);
            $table->text('description');
            $table->timestamps();
            $table->unique(['homepage_service_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_service_translations');
        Schema::dropIfExists('homepage_services');
        Schema::dropIfExists('homepage_service_locks');
    }
};
