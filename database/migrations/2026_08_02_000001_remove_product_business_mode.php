<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('business_mode');
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Product business mode removal is forward-only because discarded values cannot be restored safely.'
        );
    }
};
