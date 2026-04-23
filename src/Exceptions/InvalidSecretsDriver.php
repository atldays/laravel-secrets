<?php

declare(strict_types=1);

namespace Atldays\Secrets\Exceptions;

use Atldays\Secrets\Contracts\Driver;

class InvalidSecretsDriver extends SecretsException
{
    public static function missingClass(): self
    {
        return new self('Secrets driver class must be a non-empty string.');
    }

    public static function notConfigured(string $driver): self
    {
        return new self("Secrets driver [{$driver}] is not configured.");
    }

    public static function invalidImplementation(string $driver): self
    {
        return new self("Secrets driver [{$driver}] must implement " . Driver::class . '.');
    }
}
