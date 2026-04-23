<?php

declare(strict_types=1);

namespace Atldays\Secrets\Commands;

use Atldays\Secrets\SecretsManager;
use Atldays\Secrets\Support\SecretsStore;
use Illuminate\Console\Command;

class ClearSecretsCacheCommand extends Command
{
    protected $signature = 'secrets:clear';

    protected $description = 'Delete the secrets entry from the configured cache.';

    public function handle(SecretsManager $manager, SecretsStore $store): int
    {
        if (!$manager->clear()) {
            $this->components->warn("No cached secrets found for key [{$store->key()}].");

            return self::SUCCESS;
        }

        $this->components->info("Cleared cached secrets for key [{$store->key()}].");

        return self::SUCCESS;
    }
}
