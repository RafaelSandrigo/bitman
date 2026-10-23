<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/** O que o usuário informa ao pedir para ser developer (M18), já validado. */
final readonly class DeveloperApplication
{
    public function __construct(
        public string $name,
        public GithubProfileUrl $github,
        public string $bio,
    ) {}
}
