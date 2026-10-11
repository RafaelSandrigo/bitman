<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Resources\CurrentUserResource;
use Illuminate\Container\Attributes\CurrentUser;

final class MeController
{
    public function __invoke(#[CurrentUser] User $user): CurrentUserResource
    {
        return new CurrentUserResource($user);
    }
}
