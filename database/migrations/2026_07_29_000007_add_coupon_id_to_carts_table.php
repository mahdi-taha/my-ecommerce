<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('coupon_id')
                ->nullable()
                ->after('guest_token_hash')
                ->constrained('coupons')
                ->nullOnDelete();
        });

        $this->restoreSqliteIntegrityTriggers();
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
        });

        $this->restoreSqliteIntegrityTriggers();
    }

    private function restoreSqliteIntegrityTriggers(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS carts_integrity_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS carts_integrity_update');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER carts_integrity_insert
            BEFORE INSERT ON carts
            FOR EACH ROW
            WHEN NOT (
                ((NEW.user_id IS NOT NULL AND NEW.guest_token_hash IS NULL)
                    OR (NEW.user_id IS NULL AND NEW.guest_token_hash IS NOT NULL))
                AND NEW.expires_at > NEW.last_activity_at
            )
            BEGIN
                SELECT RAISE(ABORT, 'A cart must have exactly one owner and a valid expiration.');
            END;
        SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER carts_integrity_update
            BEFORE UPDATE ON carts
            FOR EACH ROW
            WHEN NOT (
                ((NEW.user_id IS NOT NULL AND NEW.guest_token_hash IS NULL)
                    OR (NEW.user_id IS NULL AND NEW.guest_token_hash IS NOT NULL))
                AND NEW.expires_at > NEW.last_activity_at
            )
            BEGIN
                SELECT RAISE(ABORT, 'A cart must have exactly one owner and a valid expiration.');
            END;
        SQL);
    }
};
