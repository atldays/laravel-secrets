<?php

declare(strict_types=1);

namespace Tests\Fakes\Filters;

use Atldays\Secrets\Contracts\{SecretFilter, SecretReferenceContract};

class PlainPrefixFilter implements SecretFilter
{
    public function matches(SecretReferenceContract $secret): bool
    {
        return $secret->nameStartsWith('/atldays/laravel-secrets/test/plain/');
    }
}
