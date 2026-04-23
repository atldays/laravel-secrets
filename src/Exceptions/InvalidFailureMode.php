<?php

declare(strict_types=1);

namespace Atldays\Secrets\Exceptions;

class InvalidFailureMode extends SecretsException
{
    public static function unsupported(string $mode): self
    {
        return new self("Unsupported failure mode [{$mode}].");
    }
}
