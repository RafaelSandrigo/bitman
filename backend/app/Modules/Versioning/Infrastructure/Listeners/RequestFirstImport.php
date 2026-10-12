<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure\Listeners;

use App\Modules\Catalog\Domain\Events\GameSubmitted;
use App\Modules\Versioning\Domain\GameVersion;
use App\Shared\Application\DomainEventPublisher;

/**
 * Listener síncrono: a versão 1 nasce na transação da submissão, como solicitação de importação.
 * Só cria o registro; o acesso ao repositório depende do Portão 0 (I2).
 */
final readonly class RequestFirstImport
{
    public function __construct(private DomainEventPublisher $publisher) {}

    public function handle(GameSubmitted $event): void
    {
        $version = GameVersion::requestImport($event->gameId, $event->repositoryUrl, $event->ref, $event->ownerId);
        $this->publisher->publishFrom($version);
    }
}
