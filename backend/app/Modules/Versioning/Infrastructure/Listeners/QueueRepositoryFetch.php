<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure\Listeners;

use App\Modules\Versioning\Domain\Events\ImportAuthorized;
use App\Modules\Versioning\Infrastructure\Jobs\FetchRepositoryJob;
use Illuminate\Contracts\Bus\Dispatcher;

/** Portão 0 aprovado → enfileira a busca, só depois do commit da autorização. */
final readonly class QueueRepositoryFetch
{
    public function __construct(private Dispatcher $bus) {}

    public function handle(ImportAuthorized $event): void
    {
        $this->bus->dispatch((new FetchRepositoryJob($event->versionId))->afterCommit());
    }
}
