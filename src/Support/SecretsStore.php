<?php

declare(strict_types=1);

namespace Atldays\Secrets\Support;

use Atldays\Secrets\Data\SecretsPayload;
use Illuminate\Contracts\Cache\Repository as Cache;

class SecretsStore
{
    public function __construct(
        protected Cache $cache,
        protected string $key,
        protected ?int $ttl = null,
    ) {}

    public function put(SecretsPayload $payload): void
    {
        $payload = $payload->toArray();

        if ($this->ttl === null) {
            $this->cache->forever($this->key, $payload);

            return;
        }

        $this->cache->put($this->key, $payload, $this->ttl * 60);
    }

    public function get(): SecretsPayload
    {
        $payload = $this->cache->get($this->key, []);

        return SecretsPayload::from(is_array($payload) ? $payload : []);
    }

    public function clear(): bool
    {
        return $this->cache->forget($this->key);
    }

    public function key(): string
    {
        return $this->key;
    }
}
