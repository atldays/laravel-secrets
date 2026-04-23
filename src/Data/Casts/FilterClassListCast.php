<?php

declare(strict_types=1);

namespace Atldays\Secrets\Data\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class FilterClassListCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): array
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value !== '' ? [$value] : [];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $entry): string => is_string($entry) ? trim($entry) : '',
            $value,
        )));
    }
}
