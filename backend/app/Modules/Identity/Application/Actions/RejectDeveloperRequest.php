<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\DeveloperRequest;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/** M18: rejeita o pedido com motivo (I11), que o usuário vê. Ele pode pedir de novo. */
final readonly class RejectDeveloperRequest
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $requestId, DecisionReason $reason): DeveloperRequest
    {
        $this->gate->forUser($actor)->authorize(Permission::ManageRoles->value);

        return $this->db->transaction(function () use ($actor, $requestId, $reason): DeveloperRequest {
            $request = DeveloperRequest::query()->lockForUpdate()->findOrFail($requestId);
            $request->reject($actor, $reason);
            $this->publisher->publishFrom($request);

            return $request;
        });
    }
}
