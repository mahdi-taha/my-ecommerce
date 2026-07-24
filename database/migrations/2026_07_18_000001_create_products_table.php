<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configurable_id')
                ->nullable()
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('type');
            $table->string('product_number')->nullable()->unique();
            $table->string('sku')->unique();
            $table->decimal('price', 15, 4);
            $table->decimal('special_price', 15, 4)->nullable();
            $table->dateTime('special_price_from')->nullable();
            $table->dateTime('special_price_to')->nullable();
            $table->string('business_mode')->nullable();
            $table->boolean('is_new');
            $table->boolean('is_featured');
            $table->boolean('is_visible_individually');
            $table->boolean('status');
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
