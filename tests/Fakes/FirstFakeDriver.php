<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Atldays\Secrets\Contracts\Driver;
use Illuminate\Contracts\Config\Repository as Config;

class FirstFakeDriver implements Driver
{
    public function __construct(
        protected Config $config,
    ) {}

    public function fetch(): array
    {
        $config = $this->config->get('secrets.drivers.' . self::class, []);

        return is_array($config) ? ($config['secrets'] ?? []) : [];
    }
}
