<?php

declare(strict_types=1);

namespace Asaas\Laravel\Models;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @phpstan-type CustomerData array{id?: string, name?: string, email?: string, cpfCnpj?: string, ...}
 *
 * @implements Arrayable<string, mixed>
 */
final class Customer implements Arrayable
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $cpfCnpj = null,
        public readonly ?string $externalReference = null,
        /** @var array<string, mixed> */
        public readonly array $data = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Cast::string($data['id'] ?? null),
            name: Cast::string($data['name'] ?? null),
            email: Cast::string($data['email'] ?? null),
            cpfCnpj: Cast::string($data['cpfCnpj'] ?? null),
            externalReference: Cast::string($data['externalReference'] ?? null),
            data: $data,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge($this->data, array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'cpfCnpj' => $this->cpfCnpj,
            'externalReference' => $this->externalReference,
        ], fn ($v) => $v !== null));
    }
}
