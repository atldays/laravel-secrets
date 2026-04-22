<?php

declare(strict_types=1);

namespace Atldays\Secrets\Commands;

use Atldays\Secrets\Facades\Secrets;
use Illuminate\Console\Command;

class GetSecretCommand extends Command
{
    protected $signature = 'secrets:get
        {name : Secret name}
        {--driver= : Read from a specific driver when using --fresh}
        {--fresh : Read directly from the provider instead of the local cache}
        {--reveal : Print the full secret value}
        {--raw : Print only the raw value}
        {--force : Allow sensitive output in production}';

    protected $description = 'Display one secret value from the local cache or provider.';

    public function handle(): int
    {
        $name = (string)$this->argument('name');
        $secrets = $this->option('fresh')
            ? Secrets::fetch($this->option('driver') ?: null)
            : Secrets::values();

        if (!array_key_exists($name, $secrets)) {
            $this->components->error("Secret [{$name}] was not found.");

            return self::FAILURE;
        }

        $value = $secrets[$name];

        if ($this->option('raw')) {
            if (!$this->confirmSecretsMayBeRevealed()) {
                return self::FAILURE;
            }

            $this->line($value);

            return self::SUCCESS;
        }

        if ($this->option('reveal')) {
            if (!$this->confirmSecretsMayBeRevealed()) {
                return self::FAILURE;
            }

            $this->components->twoColumnDetail($name, $value);

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail($name, $this->maskSecretForConsole($value));

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
