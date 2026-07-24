<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('attribute_id')
                ->constrained('attributes')
                ->restrictOnDelete();
            $table->foreignId('attribute_option_id')
                ->nullable()
                ->constrained('attribute_options')
                ->restrictOnDelete();
            $table->string('locale', 5)->nullable();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'attribute_id']);
            $table->index('attribute_option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};
