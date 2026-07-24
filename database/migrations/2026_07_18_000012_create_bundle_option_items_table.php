<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_option_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_option_id')
                ->constrained('bundle_options')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->decimal('default_quantity', 15, 4);
            $table->boolean('is_default');
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('price_override', 15, 4)->nullable();
            $table->timestamps();

            $table->unique(['bundle_option_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_option_items');
    }
};
