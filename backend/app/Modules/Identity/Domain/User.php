<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Events\RoleGranted;
use App\Modules\Identity\Domain\Events\RoleRevoked;
use App\Modules\Identity\Domain\Exceptions\EmailNotVerified;
use App\Modules\Identity\Domain\Exceptions\PlayerRoleIsPermanent;
use App\Modules\Identity\Domain\Exceptions\RoleAlreadyGranted;
use App\Modules\Identity\Domain\Exceptions\RoleNotGranted;
use App\Modules\Identity\Domain\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Domain\Notifications\VerifyEmailNotification;
use App\Shared\Domain\EmitsDomainEvents;
use App\Shared\Domain\RecordsDomainEvents;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Agregado de identidade. Roles só mudam pelos métodos de intenção, que validam as regras
 * e registram o evento auditável; quem despacha é a Action, dentro da transação.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable implements EmitsDomainEvents, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUlids, Notifiable, RecordsDomainEvents;

    protected string $guard_name = 'web';

    public static function register(string $name, string $email, string $password): self
    {
        $user = self::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->assignRole(Role::Player);

        return $user;
    }

    /** @param  User|null  $actor  null quando a concessão vem do console */
    public function grantRole(Role $role, ?User $actor): void
    {
        if ($this->hasRole($role)) {
            throw RoleAlreadyGranted::for($role);
        }

        if ($role !== Role::Player && ! $this->hasVerifiedEmail()) {
            throw EmailNotVerified::forRoleGrant();
        }

        $this->assignRole($role);
        $this->recordEvent(new RoleGranted($this->id, $role, $actor?->id));
    }

    public function revokeRole(Role $role, User $actor): void
    {
        if ($role === Role::Player) {
            throw PlayerRoleIsPermanent::create();
        }

        if (! $this->hasRole($role)) {
            throw RoleNotGranted::for($role);
        }

        $this->removeRole($role);
        $this->recordEvent(new RoleRevoked($this->id, $role, $actor->id));
    }

    public function holds(Permission $permission): bool
    {
        return $this->hasPermissionTo($permission);
    }

    /** @return list<Role> */
    public function roleList(): array
    {
        return array_values(array_filter(
            Role::cases(),
            fn (Role $role): bool => $this->hasRole($role),
        ));
    }

    /** @return list<Permission> */
    public function permissionList(): array
    {
        return array_values(array_filter(
            Permission::cases(),
            fn (Permission $permission): bool => $this->holds($permission),
        ));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /** @param  string  $token */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }
}
