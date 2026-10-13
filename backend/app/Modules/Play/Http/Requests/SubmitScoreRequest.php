<?php

declare(strict_types=1);

namespace App\Modules\Play\Http\Requests;

use App\Modules\Play\Domain\Exceptions\InvalidScore;
use App\Modules\Play\Domain\ScoreValue;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * S2: o score é um inteiro JSON em 0..ScoreValue::MAX. String numérica, fração (inclusive `1.0`
 * e `1e3`, que chegam como float) e valores fora da faixa são rejeitados, nunca convertidos.
 */
final class SubmitScoreRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],
            'score' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    ScoreValue::fromInt(is_int($value) ? $value : throw new InvalidScore);
                } catch (InvalidScore $e) {
                    $fail($e->getMessage());
                }
            }],
        ];
    }

    public function token(): string
    {
        return $this->string('token')->toString();
    }

    public function score(): ScoreValue
    {
        $score = $this->input('score');

        return ScoreValue::fromInt(is_int($score) ? $score : throw new InvalidScore);
    }
}
