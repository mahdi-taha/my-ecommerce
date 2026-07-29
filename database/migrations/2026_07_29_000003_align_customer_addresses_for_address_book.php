<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $invalid = DB::table('customer_addresses')
            ->whereNull('phone')
            ->orWhereNull('state')
            ->orderBy('id')
            ->pluck('id');

        if ($invalid->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot align customer addresses: phone and Governorate are missing for address IDs: '
                .$invalid->implode(', ').'. Correct these records before rerunning the migration.'
            );
        }

        if (! Schema::hasColumn('customer_addresses', 'label')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->string('label')->nullable()->after('user_id');
            });
        }

        if (! Schema::hasColumn('customer_addresses', 'is_default_shipping')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->boolean('is_default_shipping')->default(false)->after('country_code');
            });
        }

        if (! Schema::hasColumn('customer_addresses', 'is_default_billing')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->boolean('is_default_billing')->default(false)->after('is_default_shipping');
            });
        }

        if (Schema::hasColumn('customer_addresses', 'is_default')) {
            DB::table('customer_addresses')
                ->where('is_default', true)
                ->update([
                    'is_default_shipping' => true,
                    'is_default_billing' => true,
                ]);
        }

        if (! Schema::hasIndex('customer_addresses', 'customer_addresses_user_id_is_default_shipping_index')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->index(['user_id', 'is_default_shipping']);
            });
        }

        if (! Schema::hasIndex('customer_addresses', 'customer_addresses_user_id_is_default_billing_index')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->index(['user_id', 'is_default_billing']);
            });
        }

        if (Schema::hasIndex('customer_addresses', 'customer_addresses_user_id_is_default_index')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'is_default']);
            });
        }

        if (Schema::hasColumn('customer_addresses', 'is_default')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
            $table->string('state')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customer_addresses', 'is_default')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->boolean('is_default')->default(false);
            });
        }

        DB::table('customer_addresses')
            ->where('is_default_shipping', true)
            ->orWhere('is_default_billing', true)
            ->update(['is_default' => true]);

        if (! Schema::hasIndex('customer_addresses', 'customer_addresses_user_id_is_default_index')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->index(['user_id', 'is_default']);
            });
        }

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_default_shipping']);
            $table->dropIndex(['user_id', 'is_default_billing']);
            $table->dropColumn([
                'label',
                'is_default_shipping',
                'is_default_billing',
            ]);
            $table->string('phone')->nullable()->change();
            $table->string('state')->nullable()->change();
        });
    }
};
