<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Requests;

use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\Exceptions\InvalidDecisionReason;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Rejeição em qualquer portão: o motivo é obrigatório (I11). */
final class RejectionRequest extends FormRequest
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
