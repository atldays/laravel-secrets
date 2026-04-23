<?php

declare(strict_types=1);

namespace Atldays\Secrets\Filters;

use Atldays\Secrets\Contracts\{SecretFilter, SecretReferenceContract};
use Atldays\Secrets\Data\AwsSecretManagerConfig;

class AwsSecretManagerFilter implements SecretFilter
{
    public function __construct(protected AwsSecretManagerConfig $config) {}

    public function matches(SecretReferenceContract $secret): bool
    {
        if ($this->config->tags === [] && $this->config->prefixes === [] && $this->config->names === []) {
            return true;
        }

        $matched = false;

        foreach ($this->config->tags as $key => $values) {
            if ($secret->hasTagIn($key, $values)) {
                $matched = true;

                break;
            }
        }

        if ($this->config->prefixes !== []) {
            $matched = $secret->nameStartsWith($this->config->prefixes) || $matched;
        }

        if ($this->config->names !== []) {
            $matched = $secret->hasNameIn($this->config->names) || $matched;
        }

        return $matched;
    }
}
