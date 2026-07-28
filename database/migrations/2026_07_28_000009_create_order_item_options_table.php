<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->string('attribute_code');
            $table->string('attribute_name');
            $table->string('option_code');
            $table->string('option_label');
            $table->timestamps();

            $table->unique(['order_item_id', 'attribute_code']);
            $table->index(['attribute_code', 'option_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_options');
    }
};
