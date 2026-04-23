<?php

declare(strict_types=1);

namespace Tests;

use Atldays\Secrets\SecretsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Tests\Fakes\{FirstFakeDriver, SecondFakeDriver};

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            SecretsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('secrets.apply_secrets', true);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('secrets.cache.store', 'array');
        $app['config']->set('secrets.cache.key', 'tests.laravel-secrets');
        $app['config']->set('secrets.config_variables', [
            'app.key' => 'APP_KEY',
            'database.connections.pgsql.password' => 'DB_PASSWORD',
        ]);
        $app['config']->set('secrets.drivers', [
            FirstFakeDriver::class => [
                'secrets' => [
                    'APP_KEY' => 'base64:test-key',
                    'DB_PASSWORD' => 'secret-password',
                    'FIRST_ONLY' => 'first-value',
                ],
            ],
            SecondFakeDriver::class => [
                'secrets' => [
                    'DB_PASSWORD' => 'override-password',
                    'SECOND_ONLY' => 'second-value',
                ],
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearSecretsState();
    }

    protected function tearDown(): void
    {
        $this->clearSecretsState();

        parent::tearDown();
    }

    protected function clearSecretsState(): void
    {
        $this->app['cache']->store('array')->forget('tests.laravel-secrets');

        foreach (['APP_KEY', 'DB_PASSWORD', 'EXISTING_VALUE'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }
}
