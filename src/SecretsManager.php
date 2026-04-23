<?php

declare(strict_types=1);

namespace Atldays\Secrets;

use Atldays\Secrets\Contracts\Driver;
use Atldays\Secrets\Data\SecretsPayload;
use Atldays\Secrets\Exceptions\{InvalidFailureMode, InvalidSecretsDriver, SecretsException};
use Atldays\Secrets\Support\{SecretValue, SecretsStore};
use Dotenv\Repository\RepositoryInterface as DotenvRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\{App, Exceptions};

class SecretsManager
{
    public function __construct(
        protected Config $config,
        protected SecretsStore $cache,
        protected DotenvRepository $dotenv,
    ) {}

    /**
     * @return array<string, string>
     */
    public function fetch(?string $driver = null): array
    {
        if (is_string($driver) && $driver !== '') {
            return $this->resolveDriver($driver)->fetch();
        }

        $merged = [];

        foreach ($this->drivers() as $driverClass) {
            $merged = array_merge($merged, $this->resolveDriver($driverClass)->fetch());
        }

        ksort($merged);

        return $merged;
    }

    public function cache(?string $driver = null): SecretsPayload
    {
        $driver = is_string($driver) && $driver !== '' ? $driver : null;
        $secrets = $this->fetch($driver);

        ksort($secrets);

        $payload = new SecretsPayload(
            drivers: $driver ? [$driver] : $this->drivers(),
            secrets: $secrets,
        );

        $this->cache->put($payload);

        return $payload;
    }

    public function stored(): SecretsPayload
    {
        return $this->cache->get();
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        $values = [];

        foreach ($this->stored()->secrets as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $values[$key] = SecretValue::value($value);
        }

        return $values;
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    public function load(): int
    {
        if (!$this->config->get('secrets.apply_secrets', true)) {
            return 0;
        }

        try {
            $secrets = $this->values();

            foreach ($secrets as $key => $value) {
                $this->dotenv->set($key, $value);
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;

                $transformed = SecretValue::scalar($value);
                $variables = $this->config->get('secrets.config_variables', []);
                $variables = is_array($variables) ? $variables : [];

                foreach (array_keys($variables, $key, true) as $configPath) {
                    $this->config->set($configPath, $transformed);
                }
            }

            return count($secrets);
        } catch (SecretsException $exception) {
            $this->handle($exception);

            return 0;
        }
    }

    /**
     * @return list<string>
     */
    public function drivers(): array
    {
        $drivers = $this->config->get('secrets.drivers', []);

        if (!is_array($drivers) || $drivers === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $driver): string => is_string($driver) ? trim($driver) : '',
            array_keys($drivers),
        )));
    }

    public function key(): string
    {
        return $this->cache->key();
    }

    protected function handle(SecretsException $exception, ?string $mode = null): void
    {
        $mode = $mode ?: (string)$this->config->get('secrets.failure_mode', 'throw');

        match ($mode) {
            'throw' => throw $exception,
            'warn' => Exceptions::report($exception),
            'ignore' => null,
            default => throw InvalidFailureMode::unsupported($mode),
        };
    }

    protected function resolveDriver(?string $driver = null): Driver
    {
        if (!is_string($driver) || $driver === '') {
            throw InvalidSecretsDriver::missingClass();
        }

        if (!in_array($driver, $this->drivers(), true)) {
            throw InvalidSecretsDriver::notConfigured($driver);
        }

        if (!is_a($driver, Driver::class, true)) {
            throw InvalidSecretsDriver::invalidImplementation($driver);
        }

        return App::make($driver);
    }
}
