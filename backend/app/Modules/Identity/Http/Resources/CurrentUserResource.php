<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Domain\Permission;
use Illuminate\Http\Request;

/** O usuário autenticado também recebe as permissões, usadas pela SPA para montar menus e guards. */
final class CurrentUserResource extends UserResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'permissions' => array_map(fn (Permission $p): string => $p->value, $this->permissionList()),
        ];
    }
}
