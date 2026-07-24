<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_item_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_item_id')
                ->constrained('cart_items')
                ->cascadeOnDelete();
            $table->foreignId('bundle_option_item_id')
                ->constrained('bundle_option_items')
                ->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->timestamps();

            $table->unique(
                ['cart_item_id', 'bundle_option_item_id'],
                'cart_bundle_items_selection_unique'
            );
            $table->index('bundle_option_item_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER cart_bundle_items_positive_quantity_insert
                BEFORE INSERT ON cart_item_bundle_items
                FOR EACH ROW WHEN NEW.quantity <= 0
                BEGIN
                    SELECT RAISE(ABORT, 'Bundle item quantity must be greater than zero.');
                END;
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER cart_bundle_items_positive_quantity_update
                BEFORE UPDATE OF quantity ON cart_item_bundle_items
                FOR EACH ROW WHEN NEW.quantity <= 0
                BEGIN
                    SELECT RAISE(ABORT, 'Bundle item quantity must be greater than zero.');
                END;
            SQL);
        } else {
            DB::statement(
                'ALTER TABLE cart_item_bundle_items ADD CONSTRAINT cart_bundle_items_positive_quantity_check CHECK (quantity > 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_item_bundle_items');
    }
};
