<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Controllers\Admin;

use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\ApproveCommit;
use App\Modules\Versioning\Application\Actions\RejectCommit;
use App\Modules\Versioning\Application\Actions\RejectVersion;
use App\Modules\Versioning\Application\Actions\ReviewFile;
use App\Modules\Versioning\Application\Actions\ShowFileContent;
use App\Modules\Versioning\Http\Requests\RejectionRequest;
use App\Modules\Versioning\Http\Requests\ReviewFileRequest;
use App\Modules\Versioning\Http\Resources\VersionResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Portões 1 (commit) e 2 (arquivos). */
final class ReviewController
{
    public function approveCommit(string $version, #[CurrentUser] User $actor, ApproveCommit $approveCommit): VersionResource
    {
        return new VersionResource($approveCommit($actor, $version));
    }

    public function rejectCommit(string $version, RejectionRequest $request, #[CurrentUser] User $actor, RejectCommit $rejectCommit): VersionResource
    {
        return new VersionResource($rejectCommit($actor, $version, $request->reason()));
    }

    /**
     * O conteúdo vai como string JSON e a SPA o mostra como texto (J5). `no-store`: o código
     * revisado não fica em cache do navegador nem de proxies.
     */
    public function content(string $version, string $file, #[CurrentUser] User $actor, ShowFileContent $showFileContent): JsonResponse
    {
        $content = $showFileContent($actor, $version, $file);

        return response()->json(['data' => [
            'id' => $content->file->id,
            'path' => $content->file->path,
            'sha256' => $content->file->sha256,
            'size' => $content->file->size,
            'content' => $content->content,
            'change_type' => $content->file->change_type->value,
            'previous' => $content->previous === null ? null : [
                'version_number' => $content->previous->versionNumber,
                'content' => $content->previous->content,
            ],
            'previous_unavailable' => $content->previousUnavailable,
        ]])->header('Cache-Control', 'no-store');
    }

    public function reviewFile(string $version, string $file, ReviewFileRequest $request, #[CurrentUser] User $actor, ReviewFile $reviewFile): VersionResource
    {
        $review = $reviewFile($actor, $version, $file, $request->sha256(), $request->decision(), $request->reason());

        return new VersionResource($review->file->version);
    }

    public function rejectVersion(string $version, RejectionRequest $request, #[CurrentUser] User $actor, RejectVersion $rejectVersion): VersionResource
    {
        return new VersionResource($rejectVersion($actor, $version, $request->reason()));
    }
}
