<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('type', 20);
            $table->decimal('value', 15, 4);
            $table->boolean('is_active')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->decimal('minimum_subtotal', 15, 4)->nullable();
            $table->unsignedBigInteger('usage_limit')->nullable();
            $table->unsignedBigInteger('per_customer_usage_limit')->nullable();
            $table->boolean('is_first_order_only')->default(false);
            $table->timestamps();

            $table->index('is_active');
            $table->index('starts_at');
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
