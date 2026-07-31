<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('audience_code', 50);
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('event_code', 100);
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('title');
            $table->text('body');
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'read_at', 'created_at']);
            $table->index('event_code');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_notifications');
    }
};
