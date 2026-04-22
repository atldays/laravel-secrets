<?php

declare(strict_types=1);

namespace Atldays\Secrets\Support;

use JsonException;

class SecretValue
{
    public function __construct(
        protected mixed $value,
    ) {}

    public static function from(mixed $value): self
    {
        return new self($value);
    }

    public function toScalar(): mixed
    {
        return match (true) {
            $this->value === 'true' => true,
            $this->value === 'false' => false,
            $this->value === 'null' => null,
            is_numeric($this->value) && (string)(int)$this->value === (string)$this->value => (int)$this->value,
            is_numeric($this->value) => (float)$this->value,
            default => $this->value,
        };
    }

    public function __invoke(): mixed
    {
        return $this->toScalar();
    }

    /**
     * @throws JsonException
     */
    public function toString(): string
    {
        return match (true) {
            is_string($this->value) => $this->value,
            is_bool($this->value) => $this->value ? 'true' : 'false',
            is_null($this->value) => 'null',
            is_scalar($this->value) => (string)$this->value,
            default => json_encode($this->value, JSON_THROW_ON_ERROR),
        };
    }

    /**
     * @throws JsonException
     */
    public function __toString(): string
    {
        try {
            return $this->toString();
        } catch (JsonException) {
            return '';
        }
    }
}
