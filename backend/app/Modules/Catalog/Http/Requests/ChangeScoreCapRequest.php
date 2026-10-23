<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** M15: o teto é um inteiro JSON em 1..Number.MAX_SAFE_INTEGER, ou `null` para remover. */
final class ChangeScoreCapRequest extends FormRequest
{
    private const MAX = 9_007_199_254_740_991;

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'score_cap' => ['present', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && (! is_int($value) || $value < 1 || $value > self::MAX)) {
                    $fail('O teto precisa ser um inteiro entre 1 e 9.007.199.254.740.991, ou vazio.');
                }
            }],
        ];
    }

    public function cap(): ?int
    {
        $cap = $this->input('score_cap');

        return is_int($cap) ? $cap : null;
    }
}
