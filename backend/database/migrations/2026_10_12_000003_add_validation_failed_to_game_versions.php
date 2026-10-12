<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * `validation_failed`: a busca terminou, mas o relatório tem violação bloqueante. É terminal, como
 * `fetch_failed`, para o jogo não ficar travado: o dono corrige o repositório e reenvia (nova versão).
 * Exige commit e relatório (o CHECK de I13 já cobre todo estado fora da lista "antes da busca").
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE game_versions DROP CONSTRAINT game_versions_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE game_versions ADD CONSTRAINT game_versions_status_check CHECK (status IN (
                'import_requested', 'import_rejected', 'fetching', 'fetch_failed', 'validation_failed', 'awaiting_commit_review',
                'commit_rejected', 'awaiting_file_review', 'rejected', 'published', 'superseded', 'withdrawn'
            ))
            SQL);
        // Versão bloqueada não tem arquivo candidato: nada dela será revisado.
        DB::statement("ALTER TABLE game_versions ADD CONSTRAINT game_versions_validation_failed_check CHECK (status <> 'validation_failed' OR (validation_report->>'blocked')::boolean)");

        // Versões já buscadas com bloqueio (antes deste estado existir) ficavam paradas no Portão 1.
        $blocked = DB::table('game_versions')
            ->where('status', 'awaiting_commit_review')
            ->whereRaw("(validation_report->>'blocked')::boolean")
            ->pluck('id');
        DB::table('version_files')->whereIn('game_version_id', $blocked)->delete();
        DB::table('game_versions')->whereIn('id', $blocked)->update(['status' => 'validation_failed']);
        foreach ($blocked as $id) {
            Storage::disk('quarantine')->deleteDirectory(strtolower((string) $id));
        }
    }

    public function down(): void
    {
        DB::table('game_versions')->where('status', 'validation_failed')->update(['status' => 'awaiting_commit_review']);
        DB::statement('ALTER TABLE game_versions DROP CONSTRAINT game_versions_validation_failed_check');
        DB::statement('ALTER TABLE game_versions DROP CONSTRAINT game_versions_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE game_versions ADD CONSTRAINT game_versions_status_check CHECK (status IN (
                'import_requested', 'import_rejected', 'fetching', 'fetch_failed', 'awaiting_commit_review',
                'commit_rejected', 'awaiting_file_review', 'rejected', 'published', 'superseded', 'withdrawn'
            ))
            SQL);
    }
};
