<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('product_inventories')->where('reserved_quantity', '<>', 0)->exists()) {
            throw new RuntimeException(
                'Cannot remove reserved_quantity while non-zero reserved inventory exists.'
            );
        }

        Schema::table('product_inventories', function (Blueprint $table) {
            $table->dropColumn('reserved_quantity');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['type', 'created_at']);
            $table->index(['product_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['type', 'created_at']);
            $table->dropIndex(['product_id', 'type', 'created_at']);
        });

        Schema::table('product_inventories', function (Blueprint $table) {
            $table->decimal('reserved_quantity', 15, 4)
                ->default(0)
                ->after('quantity');
        });
    }
};
