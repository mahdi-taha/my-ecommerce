<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('category', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_audiences', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_event_id')
                ->constrained('notification_events')
                ->restrictOnDelete();
            $table->foreignId('notification_audience_id')
                ->constrained('notification_audiences')
                ->restrictOnDelete();
            $table->foreignId('notification_channel_id')
                ->constrained('notification_channels')
                ->restrictOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->unique([
                'notification_event_id',
                'notification_audience_id',
                'notification_channel_id',
            ], 'notification_rules_identity_unique');
            $table->index(
                ['notification_event_id', 'notification_audience_id'],
                'notification_rules_event_audience_index'
            );
            $table->index(
                ['notification_channel_id', 'is_enabled'],
                'notification_rules_channel_enabled_index'
            );
            $table->index('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
        Schema::dropIfExists('notification_audiences');
        Schema::dropIfExists('notification_channels');
        Schema::dropIfExists('notification_events');
    }
};
