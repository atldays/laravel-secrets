<?php

declare(strict_types=1);

namespace Atldays\Secrets\Data;

use Spatie\LaravelData\Data;

class SecretReference extends Data
{
    /**
     * @param array<string, string> $tags
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $driver,
        public readonly string $name,
        public readonly ?string $identifier = null,
        public readonly array $tags = [],
        public readonly array $metadata = [],
    ) {}

    public function tag(string $key): ?string
    {
        return $this->tags[$key] ?? null;
    }

    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
