<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->longText('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'The settings.value LONGTEXT migration is forward-only because narrowing to TEXT could truncate existing data.'
        );
    }
};
