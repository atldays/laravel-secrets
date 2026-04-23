<?php

declare(strict_types=1);

namespace Atldays\Secrets\Exceptions;

use Atldays\Secrets\Contracts\SecretFilter;

class InvalidSecretFilter extends SecretsException
{
    public static function invalidImplementation(string $filter): self
    {
        return new self("Secrets filter [{$filter}] must implement " . SecretFilter::class . '.');
    }
}
