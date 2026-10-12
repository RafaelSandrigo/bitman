<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Application\SubmitGameInput;
use App\Shared\Domain\BusinessRuleViolation;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * A URL e a ref são validadas pelos próprios value objects (uma única fonte das regras G1–G3).
 * A thumbnail passa aqui por uma triagem de tipo; o conteúdo é conferido pelo ThumbnailProcessor (A4).
 */
final class SubmitGameRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'description' => ['required', 'string', 'max:2000'],
            'author_name' => ['required', 'string', 'min:2', 'max:60'],
            'repository_url' => ['required', 'string', 'max:255', $this->valueObject(RepositoryUrl::fromString(...))],
            'ref' => ['required', 'string', 'max:100', $this->valueObject(GitRef::fromString(...))],
            'thumbnail' => ['required', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
        ];
    }

    public function toInput(): SubmitGameInput
    {
        $thumbnail = $this->file('thumbnail');
        // Sem caminho, o ThumbnailProcessor recusa o arquivo como imagem inválida.
        $thumbnailPath = $thumbnail instanceof UploadedFile ? $thumbnail->getRealPath() : false;

        return new SubmitGameInput(
            name: $this->string('name')->toString(),
            description: $this->string('description')->toString(),
            authorName: $this->string('author_name')->toString(),
            repositoryUrl: RepositoryUrl::fromString($this->string('repository_url')->toString()),
            ref: GitRef::fromString($this->string('ref')->toString()),
            thumbnailPath: $thumbnailPath ?: '',
        );
    }

    /** @param  Closure(string): object  $factory */
    private function valueObject(Closure $factory): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($factory): void {
            try {
                $factory(is_string($value) ? $value : '');
            } catch (BusinessRuleViolation $e) {
                $fail($e->getMessage());
            }
        };
    }
}
