<?php

declare(strict_types=1);

namespace Asaas\Laravel\Models;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Resposta de listagem paginada de parcelas (API Asaas).
 *
 * @implements Arrayable<string, mixed>
 */
final class InstallmentList implements Arrayable
{
    /** @param list<Installment> $items */
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
            $items[] = Installment::fromArray(is_array($item) ? $item : []);
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
            'data' => array_map(fn (Installment $x) => $x->toArray(), $this->items),
            'totalCount' => $this->totalCount,
            'hasMore' => $this->hasMore,
        ];
    }
}
