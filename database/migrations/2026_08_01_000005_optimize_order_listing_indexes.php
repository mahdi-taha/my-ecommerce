<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['user_id', 'placed_at', 'id']);
            $table->index(['payment_status', 'placed_at']);
            $table->index(['fulfillment_status', 'placed_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_payment_status_index');
            $table->dropIndex('orders_fulfillment_status_index');
        });

        if ($this->usesMySqlFamily()
            && ! $this->hasIndex('orders_user_id_foreign')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index('user_id', 'orders_user_id_foreign');
            });
        }
    }

    public function down(): void
    {
        if ($this->usesMySqlFamily()
            && ! $this->hasIndex('orders_user_id_foreign')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index('user_id', 'orders_user_id_foreign');
            });
        }

        if (! $this->hasIndex('orders_payment_status_index')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index('payment_status');
            });
        }

        if (! $this->hasIndex('orders_fulfillment_status_index')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index('fulfillment_status');
            });
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'placed_at', 'id']);
            $table->dropIndex(['payment_status', 'placed_at']);
            $table->dropIndex(['fulfillment_status', 'placed_at']);
        });
    }

    private function usesMySqlFamily(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('orders'))->contains('name', $name);
    }
};
