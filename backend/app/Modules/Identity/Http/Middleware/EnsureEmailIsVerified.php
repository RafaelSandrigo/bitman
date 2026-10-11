<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias "verified". Substitui o do framework, cuja mensagem é fixa em inglês;
 * a API só responde JSON, então não há redirecionamento.
 */
final class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof MustVerifyEmail || ! $user->hasVerifiedEmail()) {
            abort(403, 'Confirme seu e-mail para continuar.');
        }

        return $next($request);
    }
}
