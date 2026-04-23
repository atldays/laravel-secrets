<?php

declare(strict_types=1);

namespace Atldays\Secrets\Facades;

use Atldays\Secrets\Data\SecretsPayload;
use Atldays\Secrets\SecretsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, string> fetch(?string $driver = null) Read fresh secrets directly from the provider.
 * @method static SecretsPayload cache(?string $driver = null) Fetch fresh secrets and store the payload in the configured cache.
 * @method static array<string, string> values() Read resolved secret values from the cached payload.
 * @method static SecretsPayload stored() Read the full cached payload DTO.
 * @method static int apply() Apply cached secrets to Laravel's env repository and configured config paths.
 * @method static bool clear() Remove the cached payload from the configured cache store.
 *
 * @see SecretsManager
 */
class Secrets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SecretsManager::class;
    }
}
