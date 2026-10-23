<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** M18: pedidos para ser developer. No máximo um pendente por usuário. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developer_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 60);
            $table->string('github_url', 60);
            $table->text('bio');
            $table->string('status', 20);
            $table->foreignUlid('decided_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestampTz('created_at');

            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE developer_requests ADD CONSTRAINT developer_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
        DB::statement("ALTER TABLE developer_requests ADD CONSTRAINT developer_requests_decision_check CHECK ((status = 'pending') = (decided_at IS NULL) AND (status = 'pending') = (decided_by_id IS NULL) AND ((status = 'rejected') = (decision_reason IS NOT NULL)))");
        DB::statement("CREATE UNIQUE INDEX developer_requests_one_pending ON developer_requests (user_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_requests');
    }
};
