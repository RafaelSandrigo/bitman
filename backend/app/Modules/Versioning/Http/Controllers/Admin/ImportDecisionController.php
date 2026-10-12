<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Controllers\Admin;

use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\AuthorizeImport;
use App\Modules\Versioning\Application\Actions\RejectImport;
use App\Modules\Versioning\Http\Requests\RejectImportRequest;
use App\Modules\Versioning\Http\Resources\VersionResource;
use Illuminate\Container\Attributes\CurrentUser;

/** Portão 0 — autorização Git. */
final class ImportDecisionController
{
    public function authorize(string $version, #[CurrentUser] User $actor, AuthorizeImport $authorizeImport): VersionResource
    {
        return new VersionResource($authorizeImport($actor, $version));
    }

    public function reject(string $version, RejectImportRequest $request, #[CurrentUser] User $actor, RejectImport $rejectImport): VersionResource
    {
        return new VersionResource($rejectImport($actor, $version, $request->reason()));
    }
}
