<?php

declare(strict_types=1);

namespace Atldays\Secrets\Drivers;

use Atldays\Secrets\Contracts\Driver;
use Atldays\Secrets\Data\{AwsSecretManagerConfig, SecretReference};
use Atldays\Secrets\Exceptions\{InvalidKeyStrategy, MissingSecretIdentifier};
use Atldays\Secrets\Support\SecretValue;
use Aws\SecretsManager\SecretsManagerClient;
use Illuminate\Support\Str;
use JsonException;

class AwsSecretManager implements Driver
{
    public function __construct(
        protected AwsSecretManagerConfig $config,
    ) {}

    /**
     * @throws JsonException
     */
    public function fetch(): array
    {
        $secrets = [];

        foreach ($this->references() as $reference) {
            if (!$this->matches($reference)) {
                continue;
            }

            $secrets = array_merge($secrets, $this->normalizeSecret($reference));
        }

        ksort($secrets);

        return $secrets;
    }

    protected function createClient(): SecretsManagerClient
    {
        return new SecretsManagerClient([
            'version' => $this->config->version,
            'region' => $this->config->region,
        ]);
    }

    /**
     * @return list<SecretReference>
     */
    protected function references(): array
    {
        $references = [];
        $nextToken = null;
        $client = $this->createClient();

        do {
            $params = [];

            if (is_string($nextToken) && $nextToken !== '') {
                $params['NextToken'] = $nextToken;
            }

            $result = $client->listSecrets($params);

            foreach ($result->get('SecretList') ?? [] as $secret) {
                if (!is_array($secret)) {
                    continue;
                }

                $references[] = new SecretReference(
                    driver: 'aws-secret-manager',
                    name: (string)($secret['Name'] ?? ''),
                    identifier: $secret['ARN'] ?? $secret['Name'] ?? null,
                    tags: $this->mapTags($secret['Tags'] ?? []),
                    metadata: $secret,
                );
            }

            $nextToken = $result->get('NextToken');
        } while (is_string($nextToken) && $nextToken !== '');

        return $references;
    }

    protected function matches(SecretReference $secret): bool
    {
        $matched = false;

        if ($this->config->tags !== []) {
            $matched = $this->matchesTags($secret);
        }

        if ($this->config->prefixes !== []) {
            $matched = Str::startsWith($secret->name, $this->config->prefixes) || $matched;
        }

        if ($this->config->names !== []) {
            $matched = in_array($secret->name, $this->config->names, true) || $matched;
        }

        return $matched;
    }

    protected function matchesTags(SecretReference $secret): bool
    {
        foreach ($this->config->tags as $key => $values) {
            if (in_array((string)$secret->tag($key), $values, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     *
     * @throws JsonException
     */
    protected function normalizeSecret(SecretReference $reference): array
    {
        $result = $this->createClient()->getSecretValue([
            'SecretId' => $reference->identifier ?? throw MissingSecretIdentifier::awsSecretManager(),
        ]);

        $value = $result->get('SecretString') ?? $result->get('SecretBinary');

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !array_is_list($decoded)) {
            if (array_key_exists('name', $decoded) && array_key_exists('value', $decoded) && is_string($decoded['name'])) {
                return [
                    $decoded['name'] => SecretValue::from($decoded['value'])->toString(),
                ];
            }

            $normalized = [];

            foreach ($decoded as $key => $decodedValue) {
                if (!is_string($key) || $key === '') {
                    continue;
                }

                $normalized[$key] = SecretValue::from($decodedValue)->toString();
            }

            return $normalized;
        }

        return [
            $this->deriveKey($reference->name) => $value,
        ];
    }

    /**
     * @param iterable<mixed> $tags
     * @return array<string, string>
     */
    protected function mapTags(iterable $tags): array
    {
        $mapped = [];

        foreach ($tags as $tag) {
            if (!is_array($tag)) {
                continue;
            }

            $key = $tag['Key'] ?? null;

            if (!is_string($key) || $key === '') {
                continue;
            }

            $mapped[$key] = (string)($tag['Value'] ?? '');
        }

        return $mapped;
    }

    protected function deriveKey(string $name): string
    {
        $strategy = $this->config->keyStrategy;

        return match ($strategy) {
            'basename' => Str::afterLast($name, '/'),
            'name' => $name,
            default => throw InvalidKeyStrategy::unsupported($strategy),
        };
    }
}
