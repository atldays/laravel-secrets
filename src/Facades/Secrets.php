<?php

declare(strict_types=1);

namespace Atldays\Secrets\Facades;

use Atldays\Secrets\SecretsManager;
use Illuminate\Support\Facades\Facade;

class Secrets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SecretsManager::class;
    }
}
