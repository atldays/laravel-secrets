<?php

declare(strict_types=1);

namespace Atldays\Secrets\Data\Casts;

use Spatie\LaravelData\Casts\{Cast, Uncastable};
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class DelimitedStringListCast implements Cast
{
    /**
     * @return list<string>|Uncastable
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): array|Uncastable
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn (mixed $item): string => is_scalar($item) ? trim((string)$item) : '',
                $value,
            )));
        }

        if (!is_scalar($value)) {
            return Uncastable::create();
        }

        $value = trim((string)$value);

        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            preg_split('/\s*,\s*/', $value) ?: [],
        )));
    }
}
