<?php

declare(strict_types=1);

namespace Asaas\Laravel\Models;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Representação de um pagamento (resposta da API Asaas).
 *
 * @implements Arrayable<string, mixed>
 */
final class Payment implements Arrayable
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $customer = null,
        public readonly ?float $value = null,
        public readonly ?string $status = null,
        /** @var array<string, mixed> */
        public readonly array $data = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Cast::string($data['id'] ?? null),
            customer: Cast::string($data['customer'] ?? null),
            value: Cast::float($data['value'] ?? null),
            status: Cast::string($data['status'] ?? null),
            data: $data,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge($this->data, array_filter([
            'id' => $this->id,
            'customer' => $this->customer,
            'value' => $this->value,
            'status' => $this->status,
        ], fn ($v) => $v !== null));
    }
}
