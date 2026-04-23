<?php

declare(strict_types=1);

namespace Atldays\Secrets\Commands;

use Atldays\Secrets\SecretsManager;
use Atldays\Secrets\Support\SecretsStore;
use Illuminate\Console\Command;
use Throwable;

class CacheSecretsCommand extends Command
{
    protected $signature = 'secrets:cache
        {--driver= : Fetch secrets from a specific driver}';

    protected $description = 'Fetch secrets from the provider and store them in the configured cache.';

    public function handle(SecretsManager $manager, SecretsStore $store): int
    {
        try {
            $payload = $manager->cache($this->option('driver') ?: null);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $count = count($payload->secrets);

        $this->components->info("Cached {$count} secret(s) using cache key [{$store->key()}].");

        return self::SUCCESS;
    }
}
