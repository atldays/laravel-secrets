<?php

declare(strict_types=1);

namespace Atldays\Secrets\Exceptions;

class InvalidFilterMode extends SecretsException
{
    public static function unsupported(string $mode): self
    {
        return new self("Unsupported secrets filter mode [{$mode}].");
    }
}
