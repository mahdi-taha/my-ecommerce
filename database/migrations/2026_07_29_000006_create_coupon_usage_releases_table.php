<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usage_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_usage_id')
                ->unique()
                ->constrained('coupon_usages')
                ->restrictOnDelete();
            $table->string('reason', 50);
            $table->timestamp('released_at');
            $table->timestamps();

            $table->index('released_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usage_releases');
    }
};
