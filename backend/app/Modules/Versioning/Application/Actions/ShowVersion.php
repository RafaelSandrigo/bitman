<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\VersionDetails;
use App\Modules\Versioning\Domain\FileDecision;
use App\Modules\Versioning\Domain\FileReview;
use App\Modules\Versioning\Domain\FileReviewStatus;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionFile;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;

/** Detalhe da versão para o admin: relatório, arquivos, decisões de cada portão e histórico. */
final readonly class ShowVersion
{
    public function __construct(private Gate $gate) {}

    public function __invoke(User $actor, string $versionId): VersionDetails
    {
        $this->gate->forUser($actor)->authorize(Permission::AuthorizeImports->value);

        $version = GameVersion::query()
            ->with(['game', 'requestedBy', 'importDecidedBy', 'commitDecidedBy', 'rejectedBy', 'files.reviews.reviewer'])
            ->findOrFail($versionId);

        $previous = [];
        foreach ($version->files as $file) {
            $approval = $this->previousApproval($version, $file);
            if ($approval !== null) {
                $previous[$file->id] = $approval;
            }
        }

        return new VersionDetails($version, $previous);
    }

    /**
     * ADR 0006: só informação ("aprovado anteriormente por X"). A aprovação nunca é herdada.
     * Conta o mesmo path com o mesmo hash, aprovado (estado final) em outra versão do mesmo jogo.
     */
    private function previousApproval(GameVersion $version, VersionFile $file): ?FileReview
    {
        return FileReview::query()
            ->with(['reviewer', 'file.version'])
            ->where('decision', FileDecision::Approved)
            ->where('sha256', $file->sha256)
            ->whereHas('file', fn (Builder $other) => $other
                ->where('path', $file->path)
                ->where('review_status', FileReviewStatus::Approved)
                ->whereHas('version', fn (Builder $v) => $v->where('game_id', $version->game_id)->whereKeyNot($version->id)))
            ->latest('created_at')
            ->first();
    }
}
