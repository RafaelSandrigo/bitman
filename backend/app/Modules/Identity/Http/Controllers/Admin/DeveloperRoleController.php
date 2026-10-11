<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Application\Actions\DemoteDeveloper;
use App\Modules\Identity\Application\Actions\PromoteToDeveloper;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Container\Attributes\CurrentUser;

/** Promoção e rebaixamento de developer: a única mudança de role feita pelo painel. */
final class DeveloperRoleController
{
    public function store(string $user, #[CurrentUser] User $actor, PromoteToDeveloper $promote): UserResource
    {
        return new UserResource($promote($actor, $user));
    }

    public function destroy(string $user, #[CurrentUser] User $actor, DemoteDeveloper $demote): UserResource
    {
        return new UserResource($demote($actor, $user));
    }
}
