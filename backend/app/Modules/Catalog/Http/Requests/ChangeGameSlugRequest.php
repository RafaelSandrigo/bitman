<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Domain\Exceptions\InvalidSlug;
use App\Modules\Catalog\Domain\Slug;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class ChangeGameSlugRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    Slug::fromString(is_string($value) ? $value : '');
                } catch (InvalidSlug $e) {
                    $fail($e->getMessage());
                }
            }],
        ];
    }

    public function slug(): Slug
    {
        return Slug::fromString($this->string('slug')->toString());
    }
}
