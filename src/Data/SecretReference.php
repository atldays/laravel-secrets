<?php

declare(strict_types=1);

namespace Atldays\Secrets\Data;

use Atldays\Secrets\Contracts\SecretReferenceContract;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

class SecretReference extends Data implements SecretReferenceContract
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

    public function hasTag(string $key, ?string $value = null): bool
    {
        if (!array_key_exists($key, $this->tags)) {
            return false;
        }

        if ($value === null) {
            return true;
        }

        return $this->tag($key) === $value;
    }

    /**
     * @param list<string> $values
     */
    public function hasTagIn(string $key, array $values): bool
    {
        return in_array((string)$this->tag($key), $values, true);
    }

    public function hasName(string $name): bool
    {
        return $this->name === $name;
    }

    /**
     * @param list<string> $names
     */
    public function hasNameIn(array $names): bool
    {
        return in_array($this->name, $names, true);
    }

    /**
     * @param string|list<string> $value
     */
    public function nameStartsWith(string|array $value): bool
    {
        return Str::startsWith($this->name, $value);
    }

    /**
     * @param string|list<string> $value
     */
    public function nameEndsWith(string|array $value): bool
    {
        return Str::endsWith($this->name, $value);
    }

    /**
     * @param string|list<string> $value
     */
    public function nameContains(string|array $value): bool
    {
        foreach ((array)$value as $entry) {
            if ($entry !== '' && Str::contains($this->name, $entry)) {
                return true;
            }
        }

        return false;
    }

    public function hasIdentifier(): bool
    {
        return is_string($this->identifier) && $this->identifier !== '';
    }

    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
