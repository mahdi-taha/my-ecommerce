<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignId('parent_order_item_id')
                ->nullable()
                ->constrained('order_items')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->string('product_type');
            $table->string('sku');
            $table->string('product_number')->nullable();
            $table->string('name');
            $table->text('option_summary')->nullable();
            $table->string('image_path')->nullable();
            $table->json('configuration')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('original_unit_price', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('row_subtotal', 15, 4);
            $table->decimal('row_total', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->boolean('is_inventory_item');
            $table->timestamps();

            $table->index('parent_order_item_id');
            $table->index('product_id');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
