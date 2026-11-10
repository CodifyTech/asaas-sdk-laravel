<?php

declare(strict_types=1);

namespace Asaas\Laravel\Models;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Result of a delete operation (`{deleted, id}`).
 *
 * @implements Arrayable<string, mixed>
 */
final class DeleteResult implements Arrayable
{
    public function __construct(
        public readonly ?bool $deleted = null,
        public readonly ?string $id = null,
        /** @var array<string, mixed> */
        public readonly array $data = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            deleted: Cast::bool($data['deleted'] ?? null),
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
