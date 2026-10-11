<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Application\Actions\ListAuditEntries;
use App\Modules\Audit\Http\Requests\ListAuditEntriesRequest;
use App\Modules\Audit\Http\Resources\AuditEntryResource;
use App\Modules\Identity\Domain\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AuditEntryController
{
    public function index(ListAuditEntriesRequest $request, #[CurrentUser] User $actor, ListAuditEntries $list): AnonymousResourceCollection
    {
        return AuditEntryResource::collection($list($actor, $request->subjectType(), $request->subjectId()));
    }
}
