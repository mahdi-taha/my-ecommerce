<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->char('guest_token_hash', 64)->nullable()->unique();
            $table->dateTime('last_activity_at');
            $table->dateTime('expires_at')->index();
            $table->timestamps();

            $table->index('last_activity_at');
        });

        $this->addIntegrityConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }

    private function addIntegrityConstraints(): void
    {
        if (DB::getDriverName() === 'sqlite') {
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

            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE carts
            ADD CONSTRAINT carts_exactly_one_owner_check CHECK (
                (user_id IS NOT NULL AND guest_token_hash IS NULL)
                OR (user_id IS NULL AND guest_token_hash IS NOT NULL)
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE carts
            ADD CONSTRAINT carts_expiration_check CHECK (expires_at > last_activity_at)
        SQL);
    }
};
