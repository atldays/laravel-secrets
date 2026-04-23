<?php

declare(strict_types=1);

namespace Atldays\Secrets\Commands;

use Atldays\Secrets\Facades\Secrets;
use Atldays\Secrets\Support\SecretsStore;
use Illuminate\Console\Command;

class ListSecretsCommand extends Command
{
    protected $signature = 'secrets:list
        {--driver= : Read from a specific driver when using --fresh}
        {--fresh : Read directly from the provider instead of the configured cache}
        {--reveal : Print full secret values}
        {--force : Allow sensitive output in production}';

    protected $description = 'List secrets from the configured cache or directly from a provider.';

    public function handle(SecretsStore $store): int
    {
        $fresh = (bool)$this->option('fresh');
        $secrets = $fresh
            ? Secrets::fetch($this->option('driver') ?: null)
            : Secrets::values();

        if ($secrets === []) {
            $source = $fresh ? 'provider' : 'configured cache';

            $this->components->warn("No secrets found in {$source}.");

            return self::SUCCESS;
        }

        $revealing = (bool)$this->option('reveal');

        if ($revealing && !$this->confirmSecretsMayBeRevealed()) {
            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Showing %d secret(s) from %s.',
            count($secrets),
            $fresh ? 'the provider' : 'cache key [' . $store->key() . ']',
        ));

        foreach ($secrets as $key => $value) {
            $this->components->twoColumnDetail(
                $key,
                $revealing ? $value : $this->maskSecretForConsole($value),
            );
        }

        return self::SUCCESS;
    }

    protected function confirmSecretsMayBeRevealed(): bool
    {
        if (!$this->getLaravel()->environment('production') || $this->option('force')) {
            return true;
        }

        $this->components->error('Revealing secrets in production requires --force.');

        return false;
    }

    protected function maskSecretForConsole(string $value): string
    {
        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 2) . str_repeat('*', max($length - 4, 4)) . substr($value, -2);
    }
}
