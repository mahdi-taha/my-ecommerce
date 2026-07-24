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
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('account_type')->nullable()->after('password');
            $table->boolean('is_active')->default(true)->after('account_type');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->index(['account_type', 'is_active']);
            $table->index(['account_type', 'email_verified_at']);
        });

        DB::table('users')->update([
            'account_type' => 'admin',
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_type', 'is_active']);
            $table->dropIndex(['account_type', 'email_verified_at']);
            $table->dropUnique(['phone']);
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'account_type',
                'is_active',
                'last_login_at',
            ]);
        });
    }
};
