<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure\Jobs;

use App\Modules\Ingestion\Domain\FetchFailure;
use App\Modules\Versioning\Application\Actions\ImportRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Busca do repositório depois do Portão 0, na fila `ingestion` (o único worker com acesso à
 * rede da sandbox). Uma tentativa só: repetir a busca exige uma nova versão.
 */
final class FetchRepositoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** Acima do timeout HTTP da sandbox (90 s), para a falha chegar como `timeout` e não como morte do worker. */
    public int $timeout = 150;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $versionId)
    {
        $this->onQueue('ingestion');
    }

    public function handle(ImportRepository $import): void
    {
        $import($this->versionId);
    }

    /** Última defesa (worker morto por tempo ou memória): a versão nunca fica presa em `fetching`. */
    public function failed(?Throwable $exception): void
    {
        app(ImportRepository::class)->fail($this->versionId, FetchFailure::Internal);
    }
}
