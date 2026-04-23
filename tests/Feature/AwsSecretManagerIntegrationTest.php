<?php

declare(strict_types=1);

namespace Tests\Feature;

use Atldays\Secrets\Data\AwsSecretManagerConfig;
use Atldays\Secrets\Drivers\AwsSecretManager;
use Aws\Exception\AwsException;
use Aws\Kms\KmsClient;
use Aws\SecretsManager\SecretsManagerClient;
use Tests\TestCase;

class AwsSecretManagerIntegrationTest extends TestCase
{
    protected static bool $seeded = false;

    public function test_it_fetches_real_aws_secrets_through_a_prefix_filter(): void
    {
        $this->seedIntegrationSecrets();

        $driver = new AwsSecretManager(AwsSecretManagerConfig::from([
            'region' => $this->awsRegion(),
            'prefixes' => $this->plainPrefix(),
        ]));

        $this->assertSame([
            'APP_KEY' => 'base64:integration-key',
            'BINARY_SECRET' => 'binary-integration-value',
            'DB_PASSWORD' => 'integration-password',
            'DB_PORT' => '5432',
            'WRAPPED_APP_KEY' => 'base64:wrapped-key',
        ], $driver->fetch());
    }

    public function test_it_fetches_a_secret_encrypted_with_a_customer_managed_kms_key(): void
    {
        $this->seedIntegrationSecrets();

        $driver = new AwsSecretManager(AwsSecretManagerConfig::from([
            'region' => $this->awsRegion(),
            'names' => $this->kmsSecretName(),
        ]));

        $this->assertSame([
            'KMS_SECRET' => 'kms-integration-value',
        ], $driver->fetch());
    }

    public function test_it_reads_all_pages_when_max_results_forces_pagination(): void
    {
        $this->seedIntegrationSecrets();

        $driver = new AwsSecretManager(AwsSecretManagerConfig::from([
            'region' => $this->awsRegion(),
            'listMaxResults' => 2,
            'prefixes' => $this->paginationPrefix(),
        ]));

        $this->assertSame([
            'PAGE_SECRET_1' => 'page-value-1',
            'PAGE_SECRET_2' => 'page-value-2',
            'PAGE_SECRET_3' => 'page-value-3',
            'PAGE_SECRET_4' => 'page-value-4',
            'PAGE_SECRET_5' => 'page-value-5',
        ], $driver->fetch());
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->integrationEnabled()) {
            $this->markTestSkipped('AWS integration credentials are not enabled for this test run.');
        }
    }

    protected function integrationEnabled(): bool
    {
        return (bool)getenv('AWS_ACCESS_KEY_ID')
            && (bool)getenv('AWS_SECRET_ACCESS_KEY')
            && (bool)getenv('AWS_SECRETS_MANAGER_INTEGRATION');
    }

    protected function seedIntegrationSecrets(): void
    {
        if (self::$seeded) {
            return;
        }

        $tags = [
            ['Key' => 'application', 'Value' => 'laravel-secrets'],
            ['Key' => 'environment', 'Value' => 'testing'],
            ['Key' => 'scope', 'Value' => 'integration'],
        ];

        $this->upsertSecret(
            name: $this->plainPrefix() . 'APP_KEY',
            secretString: 'base64:integration-key',
            tags: $tags,
        );

        $this->upsertSecret(
            name: $this->plainPrefix() . 'database',
            secretString: json_encode([
                'DB_PASSWORD' => 'integration-password',
                'DB_PORT' => 5432,
            ], JSON_THROW_ON_ERROR),
            tags: $tags,
        );

        $this->upsertSecret(
            name: $this->plainPrefix() . 'wrapped',
            secretString: json_encode([
                'name' => 'WRAPPED_APP_KEY',
                'value' => 'base64:wrapped-key',
            ], JSON_THROW_ON_ERROR),
            tags: $tags,
        );

        $this->upsertSecret(
            name: $this->plainPrefix() . 'BINARY_SECRET',
            secretBinary: 'binary-integration-value',
            tags: $tags,
        );

        $this->upsertSecret(
            name: $this->kmsSecretName(),
            secretString: 'kms-integration-value',
            tags: $tags,
            kmsKeyId: $this->kmsKeyId(),
        );

        foreach (range(1, 5) as $index) {
            $this->upsertSecret(
                name: $this->paginationPrefix() . 'PAGE_SECRET_' . $index,
                secretString: 'page-value-' . $index,
                tags: $tags,
            );
        }

        self::$seeded = true;
    }

    /**
     * @param array<int, array{Key: string, Value: string}> $tags
     */
    protected function upsertSecret(
        string $name,
        ?string $secretString = null,
        ?string $secretBinary = null,
        array $tags = [],
        ?string $kmsKeyId = null,
    ): void {
        $client = $this->secretsClient();

        try {
            $client->describeSecret([
                'SecretId' => $name,
            ]);

            $update = [
                'SecretId' => $name,
            ];

            if ($kmsKeyId !== null) {
                $update['KmsKeyId'] = $kmsKeyId;
            }

            if ($secretString !== null) {
                $update['SecretString'] = $secretString;
            }

            if ($secretBinary !== null) {
                $update['SecretBinary'] = $secretBinary;
            }

            $client->updateSecret($update);
        } catch (AwsException $exception) {
            if ($exception->getAwsErrorCode() !== 'ResourceNotFoundException') {
                throw $exception;
            }

            $create = [
                'Name' => $name,
                'Tags' => $tags,
            ];

            if ($kmsKeyId !== null) {
                $create['KmsKeyId'] = $kmsKeyId;
            }

            if ($secretString !== null) {
                $create['SecretString'] = $secretString;
            }

            if ($secretBinary !== null) {
                $create['SecretBinary'] = $secretBinary;
            }

            $client->createSecret($create);

            return;
        }

        if ($tags !== []) {
            $client->tagResource([
                'SecretId' => $name,
                'Tags' => $tags,
            ]);
        }
    }

    protected function kmsKeyId(): string
    {
        $aliasName = 'alias/laravel-secrets-integration';
        $kms = $this->kmsClient();

        foreach ($kms->listAliases()->get('Aliases') ?? [] as $alias) {
            if (($alias['AliasName'] ?? null) === $aliasName && is_string($alias['TargetKeyId'] ?? null)) {
                return $alias['TargetKeyId'];
            }
        }

        $result = $kms->createKey([
            'Description' => 'Laravel Secrets integration test key',
            'KeyUsage' => 'ENCRYPT_DECRYPT',
            'KeySpec' => 'SYMMETRIC_DEFAULT',
            'Tags' => [
                ['TagKey' => 'application', 'TagValue' => 'laravel-secrets'],
                ['TagKey' => 'environment', 'TagValue' => 'testing'],
                ['TagKey' => 'scope', 'TagValue' => 'integration'],
            ],
        ]);

        $keyId = (string)$result->get('KeyMetadata')['KeyId'];

        $kms->createAlias([
            'AliasName' => $aliasName,
            'TargetKeyId' => $keyId,
        ]);

        return $keyId;
    }

    protected function secretsClient(): SecretsManagerClient
    {
        return new SecretsManagerClient([
            'version' => '2017-10-17',
            'region' => $this->awsRegion(),
        ]);
    }

    protected function kmsClient(): KmsClient
    {
        return new KmsClient([
            'version' => '2014-11-01',
            'region' => $this->awsRegion(),
        ]);
    }

    protected function awsRegion(): string
    {
        return (string)getenv('AWS_DEFAULT_REGION') ?: 'eu-central-1';
    }

    protected function secretsPrefix(): string
    {
        $prefix = (string)getenv('AWS_SECRETS_MANAGER_TEST_PREFIX');

        if ($prefix !== '') {
            return rtrim($prefix, '/') . '/';
        }

        return '/atldays/laravel-secrets/test/';
    }

    protected function plainPrefix(): string
    {
        return $this->secretsPrefix() . 'plain/';
    }

    protected function kmsSecretName(): string
    {
        return $this->secretsPrefix() . 'kms/KMS_SECRET';
    }

    protected function paginationPrefix(): string
    {
        return $this->secretsPrefix() . 'pagination/';
    }
}
