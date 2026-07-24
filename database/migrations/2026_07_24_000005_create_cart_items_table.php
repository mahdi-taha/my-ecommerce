<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('product_type', 30);
            $table->char('configuration_hash', 64);
            $table->decimal('quantity', 15, 4);
            $table->timestamps();

            $table->unique(
                ['cart_id', 'product_id', 'configuration_hash'],
                'cart_items_configuration_unique'
            );
            $table->index(['cart_id', 'created_at']);
            $table->index('product_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER cart_items_positive_quantity_insert
                BEFORE INSERT ON cart_items
                FOR EACH ROW WHEN NEW.quantity <= 0
                BEGIN
                    SELECT RAISE(ABORT, 'Cart item quantity must be greater than zero.');
                END;
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER cart_items_positive_quantity_update
                BEFORE UPDATE OF quantity ON cart_items
                FOR EACH ROW WHEN NEW.quantity <= 0
                BEGIN
                    SELECT RAISE(ABORT, 'Cart item quantity must be greater than zero.');
                END;
            SQL);
        } else {
            DB::statement(
                'ALTER TABLE cart_items ADD CONSTRAINT cart_items_positive_quantity_check CHECK (quantity > 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
