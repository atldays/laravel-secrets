<?php

declare(strict_types=1);

namespace Atldays\Secrets\Data;

use Atldays\Secrets\Data\Casts\{DelimitedKeyValueListCast, DelimitedStringListCast};
use Atldays\Secrets\Drivers\AwsSecretManager;
use Illuminate\Contracts\Config\Repository as Config;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class AwsSecretManagerConfig extends Data
{
    /**
     * @param array<string, list<string>> $tags
     * @param list<string> $prefixes
     * @param list<string> $names
     */
    public function __construct(
        public readonly string $region = 'us-east-1',
        public readonly string $version = '2017-10-17',
        public readonly string $keyStrategy = 'basename',
        #[WithCast(DelimitedKeyValueListCast::class)]
        public readonly array $tags = [],
        #[WithCast(DelimitedStringListCast::class)]
        public readonly array $prefixes = [],
        #[WithCast(DelimitedStringListCast::class)]
        public readonly array $names = [],
    ) {}

    public static function fromConfig(Config $config): self
    {
        $config = $config->get('secrets.drivers.' . AwsSecretManager::class, []);
        $config = is_array($config) ? $config : [];

        $filter = $config['filter'] ?? [];
        $filter = is_array($filter) ? $filter : [];

        $payload = array_filter([
            'region' => $config['region'] ?? null,
            'version' => $config['version'] ?? null,
            'keyStrategy' => $config['key_strategy'] ?? null,
            'tags' => $filter['tags'] ?? null,
            'prefixes' => $filter['prefixes'] ?? null,
            'names' => $filter['names'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        return self::from($payload);
    }
}
