<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Application\Actions\ListUsers;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Requests\ListUsersRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class UserController
{
    public function index(ListUsersRequest $request, #[CurrentUser] User $actor, ListUsers $listUsers): AnonymousResourceCollection
    {
        return UserResource::collection($listUsers($actor, $request->search()));
    }
}
