<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPES = [
        'cash_on_delivery' => 'offline',
        'manual_wallet_transfer' => 'manual_transfer',
        'manual_bank_transfer' => 'manual_transfer',
        'online_card' => 'gateway',
    ];

    public function up(): void
    {
        $unknownCodes = DB::table('payment_methods')
            ->whereNotIn('code', array_keys(self::TYPES))
            ->pluck('code')
            ->all();

        if ($unknownCodes !== []) {
            throw new RuntimeException(
                'Payment Method alignment requires an explicit type for these unknown codes: '
                .implode(', ', $unknownCodes).'.'
            );
        }

        DB::transaction(function (): void {
            foreach (self::TYPES as $code => $type) {
                DB::table('payment_methods')
                    ->where('code', $code)
                    ->update(['type' => $type, 'updated_at' => now()]);
            }

            $this->createMethod(
                'cash_on_delivery',
                'Cash on Delivery',
                'offline',
                false,
                1
            );
            $this->createMethod(
                'manual_wallet_transfer',
                'Manual Wallet Transfer',
                'manual_transfer',
                true,
                2
            );
            $this->createMethod(
                'manual_bank_transfer',
                'Manual Bank Transfer',
                'manual_transfer',
                true,
                3
            );

            DB::table('payment_methods')
                ->where('code', 'online_card')
                ->update([
                    'type' => 'gateway',
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('type')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('The Version 1 Payment Method alignment is forward-only.');
    }

    private function createMethod(
        string $code,
        string $name,
        string $type,
        bool $requiresPrepayment,
        int $sortOrder
    ): void {
        if (DB::table('payment_methods')->where('code', $code)->exists()) {
            DB::table('payment_methods')->where('code', $code)->update([
                'type' => $type,
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('payment_methods')->insert([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            'requires_payment_before_processing' => $requiresPrepayment,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
