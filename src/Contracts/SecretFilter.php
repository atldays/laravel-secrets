<?php

declare(strict_types=1);

namespace Atldays\Secrets\Contracts;

interface SecretFilter
{
    public function matches(SecretReferenceContract $secret): bool;
}
