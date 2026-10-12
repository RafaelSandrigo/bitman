<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Controllers\Admin;

use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\PublishVersion;
use App\Modules\Versioning\Application\Actions\WithdrawVersion;
use App\Modules\Versioning\Http\Requests\RejectionRequest;
use App\Modules\Versioning\Http\Resources\VersionResource;
use Illuminate\Container\Attributes\CurrentUser;

/** Publicação (I6–I8) e retirada (J6). */
final class PublicationController
{
    public function publish(string $version, #[CurrentUser] User $actor, PublishVersion $publishVersion): VersionResource
    {
        return new VersionResource($publishVersion($actor, $version));
    }

    public function withdraw(string $version, RejectionRequest $request, #[CurrentUser] User $actor, WithdrawVersion $withdrawVersion): VersionResource
    {
        return new VersionResource($withdrawVersion($actor, $version, $request->reason()));
    }
}
