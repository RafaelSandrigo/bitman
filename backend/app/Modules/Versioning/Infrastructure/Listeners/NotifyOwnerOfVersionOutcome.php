<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure\Listeners;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Ingestion\Domain\FetchFailure;
use App\Modules\Versioning\Domain\Events\CommitRejected;
use App\Modules\Versioning\Domain\Events\ImportRejected;
use App\Modules\Versioning\Domain\Events\RepositoryFetched;
use App\Modules\Versioning\Domain\Events\RepositoryFetchFailed;
use App\Modules\Versioning\Domain\Events\VersionPublished;
use App\Modules\Versioning\Domain\Events\VersionRejected;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Infrastructure\Notifications\VersionOutcomeNotification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * M3: o dono fica sabendo quando a versão para (recusada, rejeitada, falhou, reprovada) ou vai ao
 * ar, sem precisar abrir "Meus jogos". Depois do commit: nunca avisa de algo que foi desfeito.
 */
final readonly class NotifyOwnerOfVersionOutcome implements ShouldHandleEventsAfterCommit
{
    public function handle(ImportRejected|CommitRejected|VersionRejected|RepositoryFetchFailed|RepositoryFetched|VersionPublished $event): void
    {
        if ($event instanceof RepositoryFetched && ! $event->blocked) {
            return;
        }

        $game = Game::query()->with('owner')->find($event->gameId);
        if ($game === null || ! $game->owner->hasVerifiedEmail()) {
            return;
        }

        $version = "{$game->name} v{$this->numberOf($event)}";
        [$subject, $summary, $detail] = $this->describe($event, $version);
        $game->owner->notify(new VersionOutcomeNotification($subject, $summary, $detail));
    }

    private function numberOf(ImportRejected|CommitRejected|VersionRejected|RepositoryFetchFailed|RepositoryFetched|VersionPublished $event): int
    {
        if ($event instanceof RepositoryFetchFailed || $event instanceof RepositoryFetched) {
            return (int) GameVersion::query()->whereKey($event->versionId)->value('number');
        }

        return $event->number;
    }

    /** @return array{string, string, string|null} assunto, resumo e detalhe (o motivo do admin ou a falha) */
    private function describe(object $event, string $version): array
    {
        return match (true) {
            $event instanceof ImportRejected => ["{$version}: importação recusada", 'A administração não autorizou buscar o repositório desta versão.', "Motivo: {$event->reason}"],
            $event instanceof CommitRejected => ["{$version}: commit rejeitado", 'O commit buscado não foi aprovado na revisão.', "Motivo: {$event->reason}"],
            $event instanceof VersionRejected => ["{$version}: versão rejeitada", 'A versão não foi aprovada na revisão dos arquivos.', "Motivo: {$event->reason}"],
            $event instanceof RepositoryFetchFailed => ["{$version}: a busca do repositório falhou", 'Não foi possível buscar o repositório. Corrija e envie de novo.', FetchFailure::tryFrom($event->failure)?->message()],
            $event instanceof RepositoryFetched => ["{$version}: reprovada na validação", "O repositório viola {$event->violations} regra(s) obrigatória(s). Veja quais em \"Meus jogos\", corrija e envie de novo.", null],
            default => ["{$version} está no ar", 'A versão foi aprovada e publicada. Já dá para jogar.', null],
        };
    }
}
