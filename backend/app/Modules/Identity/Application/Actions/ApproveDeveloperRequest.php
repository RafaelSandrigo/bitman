<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\DeveloperRequest;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/** M18: aprova o pedido e concede a role developer (auditado). */
final readonly class ApproveDeveloperRequest
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $requestId): DeveloperRequest
    {
        $this->gate->forUser($actor)->authorize(Permission::ManageRoles->value);

        return $this->db->transaction(function () use ($actor, $requestId): DeveloperRequest {
            $request = DeveloperRequest::query()->lockForUpdate()->findOrFail($requestId);
            $request->approve($actor);
            $this->publisher->publishFrom($request->user);
            $this->publisher->publishFrom($request);

            return $request;
        });
    }
}
