<?php

declare(strict_types=1);

namespace Asaas\Laravel\Models;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Generic API resource (status, QR code, limits, configs): `id` when present plus the raw payload.
 *
 * @implements Arrayable<string, mixed>
 */
final class GenericResource implements Arrayable
{
    public function __construct(
        public readonly ?string $id = null,
        /** @var array<string, mixed> */
        public readonly array $data = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Cast::string($data['id'] ?? null),
            data: $data,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
