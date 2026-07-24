<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_account')->nullable()->after('account_type');
        });

        DB::table('users')->update(['has_account' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type', 20)->change();
            $table->boolean('has_account')->nullable(false)->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->dropUnique('users_phone_unique');
            $table->dropIndex(['account_type', 'is_active']);
            $table->index('phone');
            $table->index(['account_type', 'has_account', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_type', 'has_account', 'is_active']);
            $table->dropIndex(['phone']);
            $table->index(['account_type', 'is_active']);
            $table->unique('phone');
            $table->string('account_type', 50)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
            $table->dropColumn('has_account');
        });
    }
};
