<?php

declare(strict_types=1);

namespace Tests\Feature;

use Atldays\Secrets\Data\AwsSecretManagerConfig;
use Atldays\Secrets\Filters\AwsSecretManagerFilter;
use Atldays\Secrets\SecretsManager;
use Tests\Fakes\FirstFakeDriver;
use Tests\TestCase;

class SecretsHydrationTest extends TestCase
{
    public function test_it_loads_cached_secrets_into_environment_and_config(): void
    {
        $manager = $this->app->make(SecretsManager::class);

        $manager->cache();
        $manager->load();

        $this->assertSame('base64:test-key', getenv('APP_KEY'));
        $this->assertSame('override-password', getenv('DB_PASSWORD'));
        $this->assertSame('base64:test-key', config('app.key'));
        $this->assertSame('override-password', config('database.connections.pgsql.password'));
    }

    public function test_it_transforms_scalar_string_values_for_config_variables(): void
    {
        $this->app['config']->set('secrets.drivers', [
            FirstFakeDriver::class => [
                'secrets' => [
                    'APP_KEY' => 'base64:test-key',
                    'DB_PASSWORD' => 'false',
                ],
            ],
        ]);

        $manager = $this->app->make(SecretsManager::class);

        $manager->cache();
        $manager->load();

        $this->assertFalse(config('database.connections.pgsql.password'));
    }

    public function test_it_casts_aws_filter_configuration_into_structured_values(): void
    {
        $config = AwsSecretManagerConfig::from([
            'tags' => 'application:api|admin,environment:production',
            'prefixes' => 'project/prod/, project/shared/',
            'names' => 'DB_PASSWORD, APP_KEY',
            'filters' => AwsSecretManagerFilter::class,
        ]);

        $this->assertSame([AwsSecretManagerFilter::class], $config->filters);
        $this->assertSame([
            'application' => ['api', 'admin'],
            'environment' => ['production'],
        ], $config->tags);
        $this->assertSame(['project/prod/', 'project/shared/'], $config->prefixes);
        $this->assertSame(['DB_PASSWORD', 'APP_KEY'], $config->names);
    }
}
