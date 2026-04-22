<?php

declare(strict_types=1);

namespace Atldays\Secrets\Data\Casts;

use Spatie\LaravelData\Casts\{Cast, Uncastable};
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class DelimitedKeyValueListCast implements Cast
{
    /**
     * @return array<string, list<string>>|Uncastable
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): array|Uncastable
    {
        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        if (!is_scalar($value)) {
            return Uncastable::create();
        }

        $value = trim((string)$value);

        if ($value === '') {
            return [];
        }

        $normalized = [];

        foreach (preg_split('/\s*,\s*/', $value) ?: [] as $entry) {
            if ($entry === '') {
                continue;
            }

            $parts = preg_split('/\s*[:=]\s*/', $entry, 2) ?: [];

            if (count($parts) !== 2) {
                continue;
            }

            [$key, $rawValues] = $parts;
            $key = trim($key);
            $rawValues = trim($rawValues);

            if ($key === '' || $rawValues === '') {
                continue;
            }

            $values = array_values(array_filter(array_map(
                static fn (string $item): string => trim($item),
                preg_split('/\s*\|\s*/', $rawValues) ?: [],
            )));

            if ($values === []) {
                continue;
            }

            $normalized[$key] = $values;
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $value
     * @return array<string, list<string>>
     */
    protected function normalizeArray(array $value): array
    {
        $normalized = [];

        foreach ($value as $key => $items) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }

            $items = is_array($items) ? $items : [$items];
            $items = array_values(array_filter(array_map(
                static fn (mixed $item): string => is_scalar($item) ? trim((string)$item) : '',
                $items,
            )));

            if ($items === []) {
                continue;
            }

            $normalized[trim($key)] = $items;
        }

        return $normalized;
    }
}
