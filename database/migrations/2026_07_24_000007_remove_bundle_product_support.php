<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureBundleDataIsAbsent();

        Schema::dropIfExists('cart_item_bundle_items');
        Schema::dropIfExists('bundle_option_translations');
        Schema::dropIfExists('bundle_option_items');
        Schema::dropIfExists('bundle_options');
    }

    public function down(): void
    {
        Schema::create('bundle_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('type');
            $table->boolean('is_required');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('min_selections');
            $table->unsignedInteger('max_selections');
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('bundle_option_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_option_id')
                ->constrained('bundle_options')
                ->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->timestamps();

            $table->unique(['bundle_option_id', 'locale']);
        });

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

        $this->addBundleQuantityConstraint();
    }

    private function ensureBundleDataIsAbsent(): void
    {
        $bundleProducts = DB::table('products')
            ->where('type', 'bundle')
            ->count();
        $bundleCartItems = DB::table('cart_items')
            ->where('product_type', 'bundle')
            ->count();
        $bundleSelections = DB::table('cart_item_bundle_items')->count();

        if ($bundleProducts > 0 || $bundleCartItems > 0 || $bundleSelections > 0) {
            throw new RuntimeException(
                'Bundle Product support cannot be removed while Bundle Products, '
                .'Bundle CartItems, or Bundle Cart selections still exist. '
                ."Found products={$bundleProducts}, cart_items={$bundleCartItems}, "
                ."cart_selections={$bundleSelections}."
            );
        }
    }

    private function addBundleQuantityConstraint(): void
    {
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

            return;
        }

        DB::statement(
            'ALTER TABLE cart_item_bundle_items '
            .'ADD CONSTRAINT cart_bundle_items_positive_quantity_check CHECK (quantity > 0)'
        );
    }
};
