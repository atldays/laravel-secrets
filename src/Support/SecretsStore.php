<?php

declare(strict_types=1);

namespace Atldays\Secrets\Support;

use Atldays\Secrets\Data\SecretsPayload;
use Illuminate\Contracts\Cache\Repository as Cache;

class SecretsStore
{
    public function __construct(
        protected Cache $cache,
        ?string $key = null,
        int|string|null $ttl = null,
    ) {
        $this->key = is_string($key) && trim($key) !== '' ? trim($key) : 'laravel-secrets';
        $this->ttl = is_numeric($ttl) && (int)$ttl > 0 ? (int)$ttl : 43200;
    }

    protected string $key;

    protected int $ttl = 43200;

    public function put(SecretsPayload $payload): void
    {
        $payload = $payload->toArray();

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
