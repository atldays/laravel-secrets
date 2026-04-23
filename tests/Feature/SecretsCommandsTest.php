<?php

declare(strict_types=1);

namespace Tests\Feature;

use Atldays\Secrets\SecretsManager;
use Tests\Fakes\{FirstFakeDriver, SecondFakeDriver};
use Tests\TestCase;

class SecretsCommandsTest extends TestCase
{
    public function test_it_caches_secrets_into_the_configured_cache_store(): void
    {
        $this->artisan('secrets:cache')
            ->expectsOutputToContain('Cached 4 secret(s)')
            ->assertSuccessful();

        $payload = $this->app['cache']->store('array')->get('tests.laravel-secrets');

        $this->assertSame([FirstFakeDriver::class, SecondFakeDriver::class], $payload['drivers']);
        $this->assertSame('base64:test-key', $payload['secrets']['APP_KEY']);
        $this->assertSame('override-password', $payload['secrets']['DB_PASSWORD']);
        $this->assertSame('first-value', $payload['secrets']['FIRST_ONLY']);
        $this->assertSame('second-value', $payload['secrets']['SECOND_ONLY']);
    }

    public function test_it_lists_secrets_with_masked_values_by_default(): void
    {
        $this->app->make(SecretsManager::class)->cache();

        $this->artisan('secrets:list')
            ->expectsOutputToContain('APP_KEY')
            ->doesntExpectOutputToContain('base64:test-key')
            ->doesntExpectOutputToContain('secret-password')
            ->assertSuccessful();
    }

    public function test_it_reveals_single_secret_with_an_explicit_flag(): void
    {
        $this->app->make(SecretsManager::class)->cache();

        $this->artisan('secrets:get APP_KEY --reveal')
            ->expectsOutputToContain('base64:test-key')
            ->assertSuccessful();
    }

    public function test_it_can_fetch_a_single_driver_explicitly(): void
    {
        $this->artisan('secrets:cache', [
            '--driver' => FirstFakeDriver::class,
        ])
            ->expectsOutputToContain('Cached 3 secret(s)')
            ->assertSuccessful();

        $payload = $this->app['cache']->store('array')->get('tests.laravel-secrets');

        $this->assertSame([FirstFakeDriver::class], $payload['drivers']);
        $this->assertSame('secret-password', $payload['secrets']['DB_PASSWORD']);
        $this->assertArrayNotHasKey('SECOND_ONLY', $payload['secrets']);
    }

    public function test_it_clears_the_cached_entry(): void
    {
        $this->app->make(SecretsManager::class)->cache();

        $this->artisan('secrets:clear')
            ->expectsOutputToContain('Cleared cached secrets')
            ->assertSuccessful();

        $this->assertNull($this->app['cache']->store('array')->get('tests.laravel-secrets'));
    }
}
