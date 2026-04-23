<?php

declare(strict_types=1);

namespace Atldays\Secrets\Contracts;

interface Driver
{
    /**
     * @return array<string, string>
     */
    public function fetch(): array;
}
