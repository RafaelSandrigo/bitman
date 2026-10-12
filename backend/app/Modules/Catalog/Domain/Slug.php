<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

use App\Modules\Catalog\Domain\Exceptions\InvalidSlug;
use Illuminate\Support\Str;

/** Identificador público do jogo na URL (`/games/{slug}`): minúsculas, números e hífens. */
final readonly class Slug
{
    public const MAX_LENGTH = 60;

    private const FALLBACK = 'jogo';

    private function __construct(public string $value) {}

    public static function fromString(string $slug): self
    {
        if (strlen($slug) < 2 || strlen($slug) > self::MAX_LENGTH || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
            throw new InvalidSlug;
        }

        return new self($slug);
    }

    public static function fromName(string $name): self
    {
        $slug = rtrim(substr(Str::slug($name), 0, self::MAX_LENGTH), '-');

        return self::fromString(strlen($slug) >= 2 ? $slug : self::FALLBACK);
    }

    /** `snake` → `snake-2`, encurtando a base se preciso para caber no limite. */
    public function withSuffix(int $number): self
    {
        $suffix = "-{$number}";

        return self::fromString(rtrim(substr($this->value, 0, self::MAX_LENGTH - strlen($suffix)), '-').$suffix);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
