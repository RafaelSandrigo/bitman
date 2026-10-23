<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Domain\DeveloperApplication;
use App\Modules\Identity\Domain\Exceptions\InvalidGithubProfileUrl;
use App\Modules\Identity\Domain\GithubProfileUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** M18: nome, perfil do GitHub e uma breve apresentação (texto puro). */
final class RequestDeveloperRoleRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'github_url' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    GithubProfileUrl::fromString(is_string($value) ? $value : '');
                } catch (InvalidGithubProfileUrl $e) {
                    $fail($e->getMessage());
                }
            }],
            'bio' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    public function application(): DeveloperApplication
    {
        return new DeveloperApplication(
            name: trim($this->string('name')->toString()),
            github: GithubProfileUrl::fromString($this->string('github_url')->toString()),
            bio: trim($this->string('bio')->toString()),
        );
    }
}
