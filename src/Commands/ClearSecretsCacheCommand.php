<?php

declare(strict_types=1);

namespace Atldays\Secrets\Commands;

use Atldays\Secrets\SecretsManager;
use Illuminate\Console\Command;

class ClearSecretsCacheCommand extends Command
{
    protected $signature = 'secrets:clear';

    protected $description = 'Delete the secrets entry from the configured cache.';

    public function handle(SecretsManager $manager): int
    {
        if (!$manager->clear()) {
            $this->components->warn("No cached secrets found for key [{$manager->key()}].");

            return self::SUCCESS;
        }

        $this->components->info("Cleared cached secrets for key [{$manager->key()}].");

        return self::SUCCESS;
    }
}
