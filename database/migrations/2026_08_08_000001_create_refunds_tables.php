<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('refund_number')->unique();
            $table->char('idempotency_key', 64)->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_payment_id')->constrained('order_payments')->restrictOnDelete();
            $table->string('currency_code', 3);
            $table->decimal('merchandise_subtotal', 15, 4);
            $table->decimal('discount_amount', 15, 4);
            $table->decimal('tax_amount', 15, 4);
            $table->decimal('merchandise_amount', 15, 4);
            $table->decimal('return_shipping_cost', 15, 4);
            $table->string('shipping_treatment');
            $table->decimal('shipping_deduction', 15, 4);
            $table->decimal('company_shipping_loss', 15, 4);
            $table->decimal('customer_refund_amount', 15, 4);
            $table->text('reason')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at');
            $table->timestamps();

            $table->index(['order_id', 'refunded_at']);
            $table->index(['order_payment_id', 'refunded_at']);
            $table->index('refunded_at');
            $table->index('shipping_treatment');
        });

        Schema::create('refund_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refund_id')->constrained('refunds')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('subtotal_amount', 15, 4);
            $table->decimal('discount_amount', 15, 4);
            $table->decimal('tax_amount', 15, 4);
            $table->decimal('line_amount', 15, 4);
            $table->timestamps();

            $table->unique(['refund_id', 'order_item_id']);
            $table->index('order_item_id');
        });

        DB::table('document_sequences')->insertOrIgnore([
            'document_type' => 'refund',
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
        DB::table('document_sequences')->where('document_type', 'refund')->delete();
    }
};
