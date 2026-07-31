<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_email')->nullable()->change();
            $table->dropColumn('admin_notes');
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Order schema alignment is forward-only because nullable guest emails cannot be safely converted back to required values.'
        );
    }
};
