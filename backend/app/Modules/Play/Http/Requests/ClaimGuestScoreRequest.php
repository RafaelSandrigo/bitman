<?php

declare(strict_types=1);

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** O token da partida de visitante é o que prova que o score pendente é de quem o salva. */
final class ClaimGuestScoreRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:128']];
    }

    public function token(): string
    {
        return $this->string('token')->toString();
    }
}
