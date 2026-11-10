<?php

declare(strict_types=1);

namespace Asaas\Laravel\Models;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Resposta de exclusão de pagamento (API Asaas).
 *
 * @implements Arrayable<string, mixed>
 */
final class PaymentDeleteResult implements Arrayable
{
    public function __construct(
        public readonly ?bool $deleted = null,
        /** @var array<string, mixed> */
        public readonly array $data = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            deleted: Cast::bool($data['deleted'] ?? null),
            data: $data,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
