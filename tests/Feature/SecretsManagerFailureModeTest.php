<?php

declare(strict_types=1);

namespace Tests\Feature;

use Atldays\Secrets\Exceptions\{InvalidFailureMode, SecretsException};
use Atldays\Secrets\SecretsManager;
use Atldays\Secrets\Support\SecretsStore;
use Dotenv\Repository\RepositoryInterface as DotenvRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Tests\TestCase;

class SecretsManagerFailureModeTest extends TestCase
{
    public function test_it_rethrows_exceptions_in_throw_mode(): void
    {
        $manager = $this->manager();

        $this->expectException(SecretsException::class);

        $manager->callHandle(new SecretsException('throw-test'), 'throw');
    }

    public function test_it_accepts_warn_mode(): void
    {
        $manager = $this->manager();

        $manager->callHandle(new SecretsException('warn-test'), 'warn');

        $this->assertTrue(true);
    }

    public function test_it_accepts_ignore_mode(): void
    {
        $manager = $this->manager();

        $manager->callHandle(new SecretsException('ignore-test'), 'ignore');

        $this->assertTrue(true);
    }

    public function test_it_rejects_unknown_failure_modes(): void
    {
        $manager = $this->manager();

        $this->expectException(InvalidFailureMode::class);

        $manager->callHandle(new SecretsException('invalid-test'), 'invalid');
    }

    protected function manager(): TestSecretsManager
    {
        return new TestSecretsManager(
            config: $this->app->make(Config::class),
            cache: $this->app->make(SecretsStore::class),
            dotenv: $this->app->make(DotenvRepository::class),
        );
    }
}

class TestSecretsManager extends SecretsManager
{
    public function callHandle(SecretsException $exception, ?string $mode = null): void
    {
        $this->handle($exception, $mode);
    }
}
