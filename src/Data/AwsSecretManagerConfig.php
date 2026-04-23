<?php

declare(strict_types=1);

namespace Atldays\Secrets\Data;

use Atldays\Secrets\Data\Casts\{DelimitedKeyValueListCast, DelimitedStringListCast, FilterClassListCast};
use Atldays\Secrets\Drivers\AwsSecretManager;
use Atldays\Secrets\Filters\AwsSecretManagerFilter;
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
        public readonly ?int $listMaxResults = null,
        public readonly string $filterMode = 'or',
        #[WithCast(FilterClassListCast::class)]
        public readonly array $filters = [AwsSecretManagerFilter::class],
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

        $filters = $config['filter'] ?? null;
        $filterOptions = $config['filter_options'] ?? [];

        if (is_array($filters) && !array_is_list($filters)) {
            $filterOptions = $filters;
            $filters = null;
        }

        $filterOptions = is_array($filterOptions) ? $filterOptions : [];

        $payload = array_filter([
            'region' => $config['region'] ?? null,
            'version' => $config['version'] ?? null,
            'keyStrategy' => $config['key_strategy'] ?? null,
            'listMaxResults' => self::listMaxResults($config['list_max_results'] ?? null),
            'filterMode' => $config['filter_mode'] ?? null,
            'filters' => $filters,
            'tags' => $filterOptions['tags'] ?? null,
            'prefixes' => $filterOptions['prefixes'] ?? null,
            'names' => $filterOptions['names'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        return self::from($payload);
    }

    protected static function listMaxResults(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (int)$value;

        return $value > 0 ? $value : null;
    }
}
