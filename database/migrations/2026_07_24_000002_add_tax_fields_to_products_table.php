<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('use_default_tax')
                ->default(true)
                ->after('business_mode');
            $table->foreignId('tax_id')
                ->nullable()
                ->after('use_default_tax')
                ->constrained('taxes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_id');
            $table->dropColumn('use_default_tax');
        });
    }
};
