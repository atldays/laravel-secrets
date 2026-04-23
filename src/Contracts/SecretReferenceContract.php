<?php

declare(strict_types=1);

namespace Atldays\Secrets\Contracts;

interface SecretReferenceContract
{
    public function tag(string $key): ?string;

    public function hasTag(string $key, ?string $value = null): bool;

    /**
     * @param list<string> $values
     */
    public function hasTagIn(string $key, array $values): bool;

    public function hasName(string $name): bool;

    /**
     * @param list<string> $names
     */
    public function hasNameIn(array $names): bool;

    /**
     * @param string|list<string> $value
     */
    public function nameStartsWith(string|array $value): bool;

    /**
     * @param string|list<string> $value
     */
    public function nameEndsWith(string|array $value): bool;

    /**
     * @param string|list<string> $value
     */
    public function nameContains(string|array $value): bool;

    public function hasIdentifier(): bool;

    public function hasMetadata(string $key): bool;

    public function meta(string $key, mixed $default = null): mixed;
}
