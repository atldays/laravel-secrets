<?php

declare(strict_types=1);

namespace Atldays\Secrets\Data;

use Spatie\LaravelData\Data;

class SecretsPayload extends Data
{
    /**
     * @param list<string> $drivers
     * @param array<string, mixed> $secrets
     */
    public function __construct(
        public readonly array $drivers = [],
        public readonly array $secrets = [],
    ) {}
}
