<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Fortify;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Mesma resposta para e-mail cadastrado, desconhecido ou pedido repetido:
 * o "esqueci a senha" não serve para descobrir quem tem conta.
 */
final class PasswordResetLinkRequestedResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha.',
        ]);
    }
}
