<?php

namespace Asaas\Laravel\Models;

use Illuminate\Contracts\Support\Arrayable;

/** Result of a delete operation (`{deleted, id}`). */
final class DeleteResult implements Arrayable
{
    public function __construct(
        public readonly ?bool $deleted = null,
        public readonly ?string $id = null,
        public readonly array $data = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            deleted: isset($data['deleted']) ? (bool) $data['deleted'] : null,
            id: isset($data['id']) ? (string) $data['id'] : null,
            data: $data,
        );
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
