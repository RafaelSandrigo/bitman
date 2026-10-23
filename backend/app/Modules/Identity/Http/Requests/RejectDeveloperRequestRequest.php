<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\Exceptions\InvalidDecisionReason;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** M18: rejeitar um pedido para ser developer: o motivo é obrigatório (I11) e o usuário o vê. */
final class RejectDeveloperRequestRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    DecisionReason::fromString(is_string($value) ? $value : '');
                } catch (InvalidDecisionReason $e) {
                    $fail($e->getMessage());
                }
            }],
        ];
    }

    public function reason(): DecisionReason
    {
        return DecisionReason::fromString($this->string('reason')->toString());
    }
}
