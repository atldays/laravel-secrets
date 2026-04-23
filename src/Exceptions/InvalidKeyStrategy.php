<?php

declare(strict_types=1);

namespace Atldays\Secrets\Exceptions;

class InvalidKeyStrategy extends SecretsException
{
    public static function unsupported(string $strategy): self
    {
        return new self("Unsupported AWS Secret Manager key strategy [{$strategy}].");
    }
}
