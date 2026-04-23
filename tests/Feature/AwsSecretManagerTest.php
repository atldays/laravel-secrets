<?php

declare(strict_types=1);

namespace Tests\Feature;

use Atldays\Secrets\Data\AwsSecretManagerConfig;
use Atldays\Secrets\Drivers\AwsSecretManager;
use Atldays\Secrets\Exceptions\{InvalidFilterMode, InvalidSecretFilter};
use Atldays\Secrets\Filters\AwsSecretManagerFilter;
use Aws\{CommandInterface, MockHandler, Result};
use Aws\SecretsManager\SecretsManagerClient;
use Psr\Http\Message\RequestInterface;
use Tests\Fakes\Filters\{PlainPrefixFilter, ProductionTagFilter};
use Tests\Fakes\NotAFilter;
use Tests\TestCase;

class AwsSecretManagerTest extends TestCase
{
    public function test_it_fetches_all_available_secrets_when_filters_are_empty(): void
    {
        $driver = new TestAwsSecretManager(
            config: AwsSecretManagerConfig::from([]),
            fakeClient: $this->mockClient([
                function (CommandInterface $command, RequestInterface $request): Result {
                    $this->assertSame('ListSecrets', $command->getName());
                    $this->assertNull($command['NextToken'] ?? null);

                    return new Result([
                        'SecretList' => [
                            [
                                'Name' => '/atldays/laravel-secrets/test/APP_KEY',
                                'ARN' => 'arn:aws:secretsmanager:test:app-key',
                                'Tags' => [],
                            ],
                            [
                                'Name' => '/atldays/laravel-secrets/test/database',
                                'ARN' => 'arn:aws:secretsmanager:test:database',
                                'Tags' => [],
                            ],
                        ],
                        'NextToken' => 'next-page',
                    ]);
                },
                function (CommandInterface $command, RequestInterface $request): Result {
                    $this->assertSame('ListSecrets', $command->getName());
                    $this->assertSame('next-page', $command['NextToken']);

                    return new Result([
                        'SecretList' => [
                            [
                                'Name' => '/atldays/laravel-secrets/test/BINARY_SECRET',
                                'ARN' => 'arn:aws:secretsmanager:test:binary',
                                'Tags' => [],
                            ],
                        ],
                    ]);
                },
                function (CommandInterface $command, RequestInterface $request): Result {
                    $this->assertSame('GetSecretValue', $command->getName());
                    $this->assertSame('arn:aws:secretsmanager:test:app-key', $command['SecretId']);

                    return new Result([
                        'SecretString' => 'base64:test-key',
                    ]);
                },
                function (CommandInterface $command, RequestInterface $request): Result {
                    $this->assertSame('GetSecretValue', $command->getName());
                    $this->assertSame('arn:aws:secretsmanager:test:database', $command['SecretId']);

                    return new Result([
                        'SecretString' => '{"DB_PASSWORD":"secret-password","DB_PORT":5432}',
                    ]);
                },
                function (CommandInterface $command, RequestInterface $request): Result {
                    $this->assertSame('GetSecretValue', $command->getName());
                    $this->assertSame('arn:aws:secretsmanager:test:binary', $command['SecretId']);

                    return new Result([
                        'SecretBinary' => base64_encode('binary-value'),
                    ]);
                },
            ]),
        );

        $this->assertSame([
            'APP_KEY' => 'base64:test-key',
            'BINARY_SECRET' => 'binary-value',
            'DB_PASSWORD' => 'secret-password',
            'DB_PORT' => '5432',
        ], $driver->fetch());
    }

    public function test_it_passes_max_results_to_the_list_secrets_request_when_configured(): void
    {
        $driver = new TestAwsSecretManager(
            config: AwsSecretManagerConfig::from([
                'listMaxResults' => 2,
            ]),
            fakeClient: $this->mockClient([
                function (CommandInterface $command, RequestInterface $request): Result {
                    $this->assertSame('ListSecrets', $command->getName());
                    $this->assertSame(2, $command['MaxResults']);
                    $this->assertNull($command['NextToken'] ?? null);

                    return new Result([
                        'SecretList' => [
                            [
                                'Name' => '/atldays/laravel-secrets/test/APP_KEY',
                                'ARN' => 'arn:aws:secretsmanager:test:app-key',
                                'Tags' => [],
                            ],
                        ],
                    ]);
                },
                new Result([
                    'SecretString' => 'base64:test-key',
                ]),
            ]),
        );

        $this->assertSame([
            'APP_KEY' => 'base64:test-key',
        ], $driver->fetch());
    }

    public function test_it_applies_or_matching_for_tags_prefixes_and_names(): void
    {
        $driver = new TestAwsSecretManager(
            config: AwsSecretManagerConfig::from([
                'filters' => AwsSecretManagerFilter::class,
                'tags' => 'application:api',
                'prefixes' => '/atldays/laravel-secrets/prefix/',
                'names' => '/atldays/laravel-secrets/exact/NAME_SECRET',
            ]),
            fakeClient: $this->mockClient([
                new Result([
                    'SecretList' => [
                        [
                            'Name' => '/atldays/laravel-secrets/tag/TAG_SECRET',
                            'ARN' => 'arn:tag',
                            'Tags' => [
                                ['Key' => 'application', 'Value' => 'api'],
                            ],
                        ],
                        [
                            'Name' => '/atldays/laravel-secrets/prefix/PREFIX_SECRET',
                            'ARN' => 'arn:prefix',
                            'Tags' => [],
                        ],
                        [
                            'Name' => '/atldays/laravel-secrets/exact/NAME_SECRET',
                            'ARN' => 'arn:name',
                            'Tags' => [],
                        ],
                        [
                            'Name' => '/atldays/laravel-secrets/ignored/IGNORED_SECRET',
                            'ARN' => 'arn:ignored',
                            'Tags' => [],
                        ],
                    ],
                ]),
                function (CommandInterface $command, RequestInterface $request): Result {
                    $this->assertSame('arn:tag', $command['SecretId']);

                    return new Result(['SecretString' => 'tag-value']);
                },
                function (CommandInterface $command, RequestInterface $request): Result {
                    $this->assertSame('arn:prefix', $command['SecretId']);

                    return new Result(['SecretString' => 'prefix-value']);
                },
                function (CommandInterface $command, RequestInterface $request): Result {
                    $this->assertSame('arn:name', $command['SecretId']);

                    return new Result(['SecretString' => 'name-value']);
                },
            ]),
        );

        $this->assertSame([
            'NAME_SECRET' => 'name-value',
            'PREFIX_SECRET' => 'prefix-value',
            'TAG_SECRET' => 'tag-value',
        ], $driver->fetch());
    }

    public function test_it_normalizes_name_value_secret_wrappers(): void
    {
        $driver = new TestAwsSecretManager(
            config: AwsSecretManagerConfig::from([
                'filters' => AwsSecretManagerFilter::class,
                'names' => '/atldays/laravel-secrets/test/app-key-wrapper',
            ]),
            fakeClient: $this->mockClient([
                new Result([
                    'SecretList' => [
                        [
                            'Name' => '/atldays/laravel-secrets/test/app-key-wrapper',
                            'ARN' => 'arn:wrapper',
                            'Tags' => [],
                        ],
                    ],
                ]),
                function (CommandInterface $command, RequestInterface $request): Result {
                    $this->assertSame('arn:wrapper', $command['SecretId']);

                    return new Result([
                        'SecretString' => '{"name":"APP_KEY","value":"base64:test-key"}',
                    ]);
                },
            ]),
        );

        $this->assertSame([
            'APP_KEY' => 'base64:test-key',
        ], $driver->fetch());
    }

    public function test_it_reuses_the_same_sdk_client_instance_during_fetch(): void
    {
        $driver = new TestAwsSecretManager(
            config: AwsSecretManagerConfig::from([]),
            fakeClient: $this->mockClient([
                new Result([
                    'SecretList' => [
                        [
                            'Name' => '/atldays/laravel-secrets/test/APP_KEY',
                            'ARN' => 'arn:aws:secretsmanager:test:app-key',
                            'Tags' => [],
                        ],
                    ],
                ]),
                new Result([
                    'SecretString' => 'base64:test-key',
                ]),
            ]),
        );

        $driver->fetch();

        $this->assertSame(1, $driver->createClientCalls);
    }

    public function test_service_provider_resolves_aws_config_from_the_laravel_config_repository(): void
    {
        $this->app['config']->set('secrets.drivers.' . AwsSecretManager::class, [
            'region' => 'eu-central-1',
            'version' => '2017-10-17',
            'key_strategy' => 'name',
            'list_max_results' => 2,
            'filter' => AwsSecretManagerFilter::class,
            'filter_mode' => 'or',
            'filter_options' => [
                'tags' => 'application:api',
                'prefixes' => '/atldays/laravel-secrets/test/',
                'names' => 'APP_KEY',
            ],
        ]);

        $config = $this->app->make(AwsSecretManagerConfig::class);

        $this->assertSame('eu-central-1', $config->region);
        $this->assertSame('2017-10-17', $config->version);
        $this->assertSame('name', $config->keyStrategy);
        $this->assertSame(2, $config->listMaxResults);
        $this->assertSame('or', $config->filterMode);
        $this->assertSame([AwsSecretManagerFilter::class], $config->filters);
        $this->assertSame(['application' => ['api']], $config->tags);
        $this->assertSame(['/atldays/laravel-secrets/test/'], $config->prefixes);
        $this->assertSame(['APP_KEY'], $config->names);
    }

    public function test_it_can_combine_multiple_custom_filters_with_and_mode(): void
    {
        $driver = new TestAwsSecretManager(
            config: AwsSecretManagerConfig::from([
                'filterMode' => 'and',
                'filters' => [
                    ProductionTagFilter::class,
                    PlainPrefixFilter::class,
                ],
            ]),
            fakeClient: $this->mockClient([
                new Result([
                    'SecretList' => [
                        [
                            'Name' => '/atldays/laravel-secrets/test/plain/APP_KEY',
                            'ARN' => 'arn:plain-production',
                            'Tags' => [
                                ['Key' => 'environment', 'Value' => 'production'],
                            ],
                        ],
                        [
                            'Name' => '/atldays/laravel-secrets/test/plain/IGNORED',
                            'ARN' => 'arn:plain-staging',
                            'Tags' => [
                                ['Key' => 'environment', 'Value' => 'staging'],
                            ],
                        ],
                        [
                            'Name' => '/atldays/laravel-secrets/test/other/IGNORED',
                            'ARN' => 'arn:other-production',
                            'Tags' => [
                                ['Key' => 'environment', 'Value' => 'production'],
                            ],
                        ],
                    ],
                ]),
                new Result([
                    'SecretString' => 'base64:test-key',
                ]),
            ]),
        );

        $this->assertSame([
            'APP_KEY' => 'base64:test-key',
        ], $driver->fetch());
    }

    public function test_it_can_combine_multiple_custom_filters_with_or_mode(): void
    {
        $driver = new TestAwsSecretManager(
            config: AwsSecretManagerConfig::from([
                'filterMode' => 'or',
                'filters' => [
                    ProductionTagFilter::class,
                    PlainPrefixFilter::class,
                ],
            ]),
            fakeClient: $this->mockClient([
                new Result([
                    'SecretList' => [
                        [
                            'Name' => '/atldays/laravel-secrets/test/plain/APP_KEY',
                            'ARN' => 'arn:plain-staging',
                            'Tags' => [
                                ['Key' => 'environment', 'Value' => 'staging'],
                            ],
                        ],
                        [
                            'Name' => '/atldays/laravel-secrets/test/other/DB_PASSWORD',
                            'ARN' => 'arn:other-production',
                            'Tags' => [
                                ['Key' => 'environment', 'Value' => 'production'],
                            ],
                        ],
                    ],
                ]),
                new Result([
                    'SecretString' => 'base64:test-key',
                ]),
                new Result([
                    'SecretString' => 'secret-password',
                ]),
            ]),
        );

        $this->assertSame([
            'APP_KEY' => 'base64:test-key',
            'DB_PASSWORD' => 'secret-password',
        ], $driver->fetch());
    }

    public function test_it_rejects_invalid_filter_classes(): void
    {
        $driver = new TestAwsSecretManager(
            config: AwsSecretManagerConfig::from([
                'filters' => [
                    NotAFilter::class,
                ],
            ]),
            fakeClient: $this->mockClient([
                new Result([
                    'SecretList' => [
                        [
                            'Name' => '/atldays/laravel-secrets/test/plain/APP_KEY',
                            'ARN' => 'arn:plain',
                            'Tags' => [],
                        ],
                    ],
                ]),
            ]),
        );

        $this->expectException(InvalidSecretFilter::class);

        $driver->fetch();
    }

    public function test_it_rejects_unsupported_filter_modes(): void
    {
        $driver = new TestAwsSecretManager(
            config: AwsSecretManagerConfig::from([
                'filterMode' => 'invalid',
                'filters' => [
                    PlainPrefixFilter::class,
                ],
            ]),
            fakeClient: $this->mockClient([
                new Result([
                    'SecretList' => [
                        [
                            'Name' => '/atldays/laravel-secrets/test/plain/APP_KEY',
                            'ARN' => 'arn:plain',
                            'Tags' => [],
                        ],
                    ],
                ]),
            ]),
        );

        $this->expectException(InvalidFilterMode::class);

        $driver->fetch();
    }

    /**
     * @param list<Result|callable> $queue
     */
    protected function mockClient(array $queue): SecretsManagerClient
    {
        return new SecretsManagerClient([
            'version' => '2017-10-17',
            'region' => 'eu-central-1',
            'credentials' => [
                'key' => 'testing',
                'secret' => 'testing',
            ],
            'handler' => new MockHandler($queue),
        ]);
    }
}

class TestAwsSecretManager extends AwsSecretManager
{
    public int $createClientCalls = 0;

    public function __construct(
        AwsSecretManagerConfig $config,
        protected SecretsManagerClient $fakeClient,
    ) {
        parent::__construct($config);
    }

    protected function createClient(): SecretsManagerClient
    {
        $this->createClientCalls++;

        return $this->fakeClient;
    }
}
