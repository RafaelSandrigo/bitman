<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Fortify;

use App\Modules\Identity\Application\Actions\RegisterPlayer;
use App\Modules\Identity\Domain\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Adaptador do Fortify: valida a entrada e repassa só os campos esperados ao caso de uso.
 */
final readonly class CreateNewUser implements CreatesNewUsers
{
    public function __construct(private RegisterPlayer $registerPlayer) {}

    /** @param  array<string, mixed>  $input */
    public function create(array $input): User
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ])->validate();

        return ($this->registerPlayer)(trim($data['name']), $data['email'], $data['password']);
    }
}
