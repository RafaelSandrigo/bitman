<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Fortify;

use App\Modules\Identity\Domain\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

final class ResetUserPassword implements ResetsUserPasswords
{
    /** @param  array<string, mixed>  $input */
    public function reset(User $user, array $input): void
    {
        $data = Validator::make($input, [
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ])->validate();

        $user->forceFill(['password' => $data['password']])->save();
    }
}
