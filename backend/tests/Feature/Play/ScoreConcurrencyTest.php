<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Application\Actions\SubmitScore;
use App\Modules\Play\Domain\Exceptions\SessionAlreadyScored;
use App\Modules\Play\Domain\ScoreValue;
use Illuminate\Support\Facades\DB;
use Tests\Support\Play;

/*
| Aceite da Fase 6 (S1): envios concorrentes do mesmo score resultam em exatamente um registro.
|
| Concorrência de verdade: processos separados (pcntl_fork), cada um com a própria conexão ao
| PostgreSQL, disparando o envio no mesmo instante. Por isso os dados são gravados (commit) por uma
| conexão à parte, fora da transação do RefreshDatabase, e apagados no fim.
*/

const RACE_CONNECTION = 'pgsql_race';
const RACERS = 6;

beforeEach(function (): void {
    config(['database.connections.'.RACE_CONNECTION => config('database.connections.pgsql')]);
    DB::setDefaultConnection(RACE_CONNECTION);
});

afterEach(function (): void {
    $race = DB::connection(RACE_CONNECTION);
    $race->table('games')->update(['published_version_id' => null]);
    // Só dados de domínio sem auditoria: nada aqui passa por eventos auditáveis.
    foreach (['scores', 'game_sessions', 'game_versions', 'games', 'model_has_roles', 'users'] as $table) {
        $race->table($table)->delete();
    }
    DB::setDefaultConnection('pgsql');
});

it('S1: envios simultâneos na mesma sessão gravam exatamente um score', function (): void {
    [, $version] = Play::playableGame();
    $player = User::factory()->create();
    $session = Play::openSession($player, $version);

    $startAt = microtime(true) + 0.5;
    $results = [];
    $children = [];
    foreach (range(1, RACERS) as $i) {
        $file = tempnam(sys_get_temp_dir(), 'bitman-race-');
        $pid = pcntl_fork();
        if ($pid === 0) {
            racer($file, $startAt, $player->id, $session->id, $i);
        }
        $children[$pid] = $file;
    }

    foreach ($children as $pid => $file) {
        pcntl_waitpid($pid, $status);
        $results[] = trim((string) file_get_contents($file));
        unlink($file);
    }

    sort($results);
    expect($results)->toBe(['already-scored', 'already-scored', 'already-scored', 'already-scored', 'already-scored', 'recorded'])
        ->and(DB::table('scores')->where('game_session_id', $session->id)->count())->toBe(1)
        ->and(DB::table('game_sessions')->where('id', $session->id)->value('status'))->toBe('scored');
})->skip(! function_exists('pcntl_fork'), 'pcntl indisponível');

/**
 * Processo filho: conexão própria, espera o instante combinado e envia. Nunca volta para o PHPUnit:
 * grava o resultado num arquivo e se encerra com SIGKILL, sem rodar destrutores (a conexão herdada
 * do pai continua dele).
 */
function racer(string $file, float $startAt, string $playerId, string $sessionId, int $value): never
{
    $result = 'error';
    try {
        // Mantém as conexões herdadas vivas (sem destrutor) e abre novas.
        $inherited = [DB::connection('pgsql')->getPdo(), DB::connection(RACE_CONNECTION)->getPdo()];
        DB::purge('pgsql');
        DB::purge(RACE_CONNECTION);

        $player = User::query()->findOrFail($playerId);
        time_sleep_until($startAt);
        app(SubmitScore::class)($player, $sessionId, Play::TOKEN, ScoreValue::fromInt($value));
        $result = 'recorded';
    } catch (SessionAlreadyScored) {
        $result = 'already-scored';
    } catch (Throwable $e) {
        $result = 'error: '.$e::class.' '.$e->getMessage();
    }

    file_put_contents($file, $result);
    posix_kill(getmypid(), SIGKILL);
    exit(1);
}
