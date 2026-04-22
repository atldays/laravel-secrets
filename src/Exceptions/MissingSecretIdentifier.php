<?php

declare(strict_types=1);

namespace Atldays\Secrets\Exceptions;

class MissingSecretIdentifier extends SecretsException
{
    public static function awsSecretManager(): self
    {
        return new self('AWS Secret Manager secret is missing an identifier.');
    }
}
