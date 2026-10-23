<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Application\Actions\ApproveDeveloperRequest;
use App\Modules\Identity\Application\Actions\ListDeveloperRequests;
use App\Modules\Identity\Application\Actions\RejectDeveloperRequest;
use App\Modules\Identity\Domain\DeveloperRequest;
use App\Modules\Identity\Domain\DeveloperRequestStatus;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Requests\RejectDeveloperRequestRequest;
use App\Modules\Identity\Http\Resources\DeveloperRequestResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** M18: a fila de pedidos para ser developer. */
final class DeveloperRequestController
{
    public function index(Request $request, #[CurrentUser] User $actor, ListDeveloperRequests $list): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(DeveloperRequestStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $status = DeveloperRequestStatus::tryFrom(is_string($validated['status'] ?? null) ? $validated['status'] : '') ?? DeveloperRequestStatus::Pending;
        $page = $list($actor, $status);

        return response()->json([
            'data' => array_map(fn (DeveloperRequest $item): array => (new DeveloperRequestResource($item))->forAdmin()->resolve($request), $page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function summary(#[CurrentUser] User $actor, ListDeveloperRequests $list): JsonResponse
    {
        return response()->json(['data' => ['pending' => $list->pendingCount($actor)]]);
    }

    public function approve(string $developerRequest, #[CurrentUser] User $actor, ApproveDeveloperRequest $approve): DeveloperRequestResource
    {
        return $this->present($approve($actor, $developerRequest));
    }

    public function reject(string $developerRequest, RejectDeveloperRequestRequest $request, #[CurrentUser] User $actor, RejectDeveloperRequest $reject): DeveloperRequestResource
    {
        return $this->present($reject($actor, $developerRequest, $request->reason()));
    }

    private function present(DeveloperRequest $developerRequest): DeveloperRequestResource
    {
        return (new DeveloperRequestResource($developerRequest->load(['user', 'decidedBy'])))->forAdmin();
    }
}
