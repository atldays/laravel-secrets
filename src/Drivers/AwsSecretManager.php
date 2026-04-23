<?php

declare(strict_types=1);

namespace Atldays\Secrets\Drivers;

use Atldays\Secrets\Contracts\{Driver, SecretFilter};
use Atldays\Secrets\Data\{AwsSecretManagerConfig, SecretReference};
use Atldays\Secrets\Exceptions\{InvalidFilterMode, InvalidKeyStrategy, InvalidSecretFilter, MissingSecretIdentifier};
use Atldays\Secrets\Support\SecretValue;
use Aws\SecretsManager\SecretsManagerClient;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use JsonException;

class AwsSecretManager implements Driver
{
    protected ?SecretsManagerClient $client = null;

    /**
     * @var list<SecretFilter>|null
     */
    protected ?array $filters = null;

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

    protected function client(): SecretsManagerClient
    {
        return $this->client ??= $this->createClient();
    }

    /**
     * @return list<SecretReference>
     */
    protected function references(): array
    {
        $references = [];
        $nextToken = null;
        $client = $this->client();

        do {
            $params = [];

            if ($this->config->listMaxResults !== null) {
                $params['MaxResults'] = $this->config->listMaxResults;
            }

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
        $filters = $this->filters();

        if ($filters === []) {
            return true;
        }

        $matches = array_map(
            static fn (SecretFilter $filter): bool => $filter->matches($secret),
            $filters,
        );

        return match ($this->config->filterMode) {
            'and' => !in_array(false, $matches, true),
            'or' => in_array(true, $matches, true),
            default => throw InvalidFilterMode::unsupported($this->config->filterMode),
        };
    }

    /**
     * @return array<string, string>
     *
     * @throws JsonException
     */
    protected function normalizeSecret(SecretReference $reference): array
    {
        $result = $this->client()->getSecretValue([
            'SecretId' => $reference->identifier ?? throw MissingSecretIdentifier::awsSecretManager(),
        ]);

        $value = $result->get('SecretString');

        if (!is_string($value) || $value === '') {
            $value = $this->decodeBinarySecret($result->get('SecretBinary'));
        }

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

    protected function decodeBinarySecret(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return $value;
        }

        return base64_encode($decoded) === $value ? $decoded : $value;
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

    /**
     * @return list<SecretFilter>
     */
    protected function filters(): array
    {
        if (is_array($this->filters)) {
            return $this->filters;
        }

        $this->filters = [];

        foreach ($this->config->filters as $filter) {
            if (!is_a($filter, SecretFilter::class, true)) {
                throw InvalidSecretFilter::invalidImplementation($filter);
            }

            $this->filters[] = App::make($filter, [
                'config' => $this->config,
            ]);
        }

        return $this->filters;
    }
}
