<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** Publicação: quem publicou, o `publicId` e o hash de cada arquivo publicado (I6, I11). */
final readonly class VersionPublished implements Auditable
{
    /** @param  array<string, string>  $hashes  path => sha256 */
    public function __construct(
        public string $versionId,
        public string $gameId,
        public int $number,
        public string $publicId,
        public string $commitSha,
        public array $hashes,
        public string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'versioning.version-published',
            subjectType: 'game_version',
            subjectId: $this->versionId,
            actorId: $this->actorId,
            context: ['game_id' => $this->gameId, 'number' => $this->number, 'public_id' => $this->publicId, 'commit' => $this->commitSha, ...$this->hashes],
        );
    }
}
