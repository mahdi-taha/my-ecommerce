<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')
                ->constrained('coupons')
                ->restrictOnDelete();
            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('coupon_code', 100);
            $table->string('coupon_type', 20);
            $table->decimal('coupon_value', 15, 4);
            $table->decimal('eligible_subtotal', 15, 4);
            $table->decimal('discount_amount', 15, 4);
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
            $table->index(['coupon_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
