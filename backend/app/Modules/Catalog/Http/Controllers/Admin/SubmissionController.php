<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Application\Actions\ListSubmissions;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Catalog\Http\Resources\GameResource;
use App\Modules\Identity\Domain\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class SubmissionController
{
    public function index(Request $request, #[CurrentUser] User $actor, ListSubmissions $listSubmissions): AnonymousResourceCollection
    {
        $validated = $request->validate(['status' => ['nullable', Rule::enum(GameStatus::class)], 'page' => ['nullable', 'integer', 'min:1']]);
        $status = is_string($validated['status'] ?? null) ? GameStatus::from($validated['status']) : GameStatus::Submitted;

        return GameResource::collection($listSubmissions($actor, $status));
    }
}
