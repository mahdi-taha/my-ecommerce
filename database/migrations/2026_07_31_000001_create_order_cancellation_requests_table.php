<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->boolean('pending_marker')->nullable()->default(true);
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'pending_marker']);
            $table->index(['order_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('reviewed_by');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TRIGGER order_cancellation_requests_validate_insert
                BEFORE INSERT ON order_cancellation_requests
                WHEN NEW.status NOT IN ('pending', 'approved', 'rejected')
                    OR NOT (
                        (NEW.status = 'pending' AND NEW.pending_marker = 1)
                        OR (NEW.status IN ('approved', 'rejected') AND NEW.pending_marker IS NULL)
                    )
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid cancellation request state');
                END
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER order_cancellation_requests_validate_update
                BEFORE UPDATE OF status, pending_marker ON order_cancellation_requests
                WHEN NEW.status NOT IN ('pending', 'approved', 'rejected')
                    OR NOT (
                        (NEW.status = 'pending' AND NEW.pending_marker = 1)
                        OR (NEW.status IN ('approved', 'rejected') AND NEW.pending_marker IS NULL)
                    )
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid cancellation request state');
                END
            SQL);

            return;
        }

        DB::statement(<<<'SQL'
                ALTER TABLE order_cancellation_requests
                ADD CONSTRAINT order_cancellation_requests_status_check
                CHECK (status IN ('pending', 'approved', 'rejected'))
            SQL);
        DB::statement(<<<'SQL'
                ALTER TABLE order_cancellation_requests
                ADD CONSTRAINT order_cancellation_requests_pending_marker_check
                CHECK (
                    (status = 'pending' AND pending_marker = 1)
                    OR (status IN ('approved', 'rejected') AND pending_marker IS NULL)
                )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_cancellation_requests');
    }
};
