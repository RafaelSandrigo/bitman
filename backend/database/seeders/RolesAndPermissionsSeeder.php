<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sincroniza roles e permissões com os enums do domínio (o banco espelha o código).
 * Idempotente: roda a cada deploy (make migrate).
 */
final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(PermissionRegistrar $registrar): void
    {
        $registrar->forgetCachedPermissions();

        $names = array_map(fn (Permission $p): string => $p->value, Permission::cases());

        foreach ($names as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }

        // Permissão removida do domínio deixa de existir no banco (e de quem a tinha).
        PermissionModel::query()->whereNotIn('name', $names)->delete();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')
                ->syncPermissions(array_map(fn (Permission $p): string => $p->value, $role->permissions()));
        }

        $registrar->forgetCachedPermissions();
    }
}
