<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Requests;

use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\Exceptions\InvalidDecisionReason;
use App\Modules\Versioning\Domain\FileDecision;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Portão 2: a decisão leva o SHA-256 do conteúdo que o admin viu (I5). O motivo é obrigatório na rejeição. */
final class ReviewFileRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::enum(FileDecision::class)],
            'sha256' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/D'],
            'reason' => ['nullable', 'required_if:decision,rejected', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    DecisionReason::fromString(is_string($value) ? $value : '');
                } catch (InvalidDecisionReason $e) {
                    $fail($e->getMessage());
                }
            }],
        ];
    }

    public function decision(): FileDecision
    {
        return FileDecision::from($this->string('decision')->toString());
    }

    public function sha256(): string
    {
        return $this->string('sha256')->toString();
    }

    public function reason(): ?DecisionReason
    {
        $reason = $this->input('reason');

        return is_string($reason) && $reason !== '' ? DecisionReason::fromString($reason) : null;
    }
}
