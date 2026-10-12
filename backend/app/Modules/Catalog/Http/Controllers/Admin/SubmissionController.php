<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Application\Actions\ListSubmissions;
use App\Modules\Catalog\Http\Resources\GameResource;
use App\Modules\Identity\Domain\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SubmissionController
{
    public function index(#[CurrentUser] User $actor, ListSubmissions $listSubmissions): AnonymousResourceCollection
    {
        return GameResource::collection($listSubmissions($actor));
    }
}
