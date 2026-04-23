<?php

declare(strict_types=1);

namespace Tests\Fakes\Filters;

use Atldays\Secrets\Contracts\{SecretFilter, SecretReferenceContract};

class TestingEnvironmentFilter implements SecretFilter
{
    public function matches(SecretReferenceContract $secret): bool
    {
        return $secret->hasTag('environment', 'testing');
    }
}
