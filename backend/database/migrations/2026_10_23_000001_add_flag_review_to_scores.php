<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M20: score sinalizado fica fora do ranking até o admin mantê-lo (`flag_cleared_*`) ou ocultá-lo.
 * Os sinalizados já existentes também passam a esperar a revisão (é o objetivo da M20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scores', function (Blueprint $table): void {
            $table->foreignUlid('flag_cleared_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('flag_cleared_at')->nullable();
        });

        DB::statement('ALTER TABLE scores ADD CONSTRAINT scores_flag_cleared_check CHECK (flag_cleared_at IS NULL OR (flagged AND flag_cleared_by_id IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE scores DROP CONSTRAINT scores_flag_cleared_check');
        Schema::table('scores', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('flag_cleared_by_id');
            $table->dropColumn('flag_cleared_at');
        });
    }
};
