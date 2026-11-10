<?php

declare(strict_types=1);

namespace Asaas\Laravel\Models;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Resposta de listagem paginada de clientes (API Asaas).
 *
 * @phpstan-type CustomerItem array{id?: string, name?: string, email?: string, cpfCnpj?: string, ...}
 */
/**
 * @implements Arrayable<string, mixed>
 */
final class CustomerList implements Arrayable
{
    /** @param list<Customer> $items */
    public function __construct(
        public readonly array $items = [],
        public readonly ?int $totalCount = null,
        public readonly ?bool $hasMore = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $raw = $data['data'] ?? [];
        $items = [];
        foreach (is_array($raw) ? $raw : [] as $item) {
            $items[] = Customer::fromArray(is_array($item) ? $item : []);
        }

        return new self(
            items: $items,
            totalCount: Cast::int($data['totalCount'] ?? null),
            hasMore: Cast::bool($data['hasMore'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'data' => array_map(fn (Customer $c) => $c->toArray(), $this->items),
            'totalCount' => $this->totalCount,
            'hasMore' => $this->hasMore,
        ];
    }
}
