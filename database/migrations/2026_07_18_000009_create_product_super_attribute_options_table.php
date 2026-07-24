<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_super_attribute_options', function (Blueprint $table) {
            $table->foreignId('product_super_attribute_id');
            $table->foreignId('attribute_option_id')
                ->constrained('attribute_options')
                ->restrictOnDelete();
            $table->timestamps();

            $table->foreign(
                'product_super_attribute_id',
                'psao_super_attribute_fk'
            )
                ->references('id')
                ->on('product_super_attributes')
                ->cascadeOnDelete();

            $table->primary([
                'product_super_attribute_id',
                'attribute_option_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_super_attribute_options');
    }
};
