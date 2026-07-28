<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $highestNumber = 0;
        $malformedOrders = [];

        DB::table('orders')
            ->select(['id', 'order_number'])
            ->orderBy('id')
            ->each(function (object $order) use (&$highestNumber, &$malformedOrders): void {
                if (! preg_match('/^ORD-(?:\d{4}-)?(\d+)$/', $order->order_number, $matches)) {
                    $malformedOrders[] = "{$order->id}: {$order->order_number}";

                    return;
                }

                $highestNumber = max($highestNumber, (int) $matches[1]);
            });

        if ($malformedOrders !== []) {
            throw new RuntimeException(
                'The order document sequence cannot be initialized because malformed order numbers exist: '
                .implode(', ', $malformedOrders).'.'
            );
        }

        DB::transaction(function () use ($highestNumber): void {
            $sequence = DB::table('document_sequences')
                ->where('document_type', 'order')
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                DB::table('document_sequences')
                    ->where('id', $sequence->id)
                    ->update([
                        'last_number' => max((int) $sequence->last_number, $highestNumber),
                        'updated_at' => now(),
                    ]);

                return;
            }

            DB::table('document_sequences')->insert([
                'document_type' => 'order',
                'last_number' => $highestNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('document_sequences')
            ->where('document_type', 'order')
            ->delete();
    }
};
