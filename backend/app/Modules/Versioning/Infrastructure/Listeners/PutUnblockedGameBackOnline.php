<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure\Listeners;

use App\Modules\Catalog\Domain\Events\GameUnblocked;
use App\Modules\Versioning\Application\Actions\ServeArchivedVersion;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

/**
 * Depois do commit do desbloqueio: servir antes poderia deixar o jogo no ar com o bloqueio
 * desfeito no banco. Uma falha aqui deixa o jogo fora do ar (o lado seguro) e é reportada;
 * o detalhe do jogo no admin mostra se a versão está sendo servida.
 */
final readonly class PutUnblockedGameBackOnline implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private ServeArchivedVersion $serveArchivedVersion,
        private ExceptionHandler $exceptions,
    ) {}

    public function handle(GameUnblocked $event): void
    {
        if ($event->publishedVersionId === null) {
            return;
        }

        try {
            ($this->serveArchivedVersion)($event->publishedVersionId);
        } catch (Throwable $e) {
            $this->exceptions->report($e);
        }
    }
}
