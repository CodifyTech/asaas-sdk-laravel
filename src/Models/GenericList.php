<?php

declare(strict_types=1);

namespace Asaas\Laravel\Models;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Generic list response whose items are raw arrays (code tables, history, wallets).
 *
 * @implements Arrayable<string, mixed>
 */
final class GenericList implements Arrayable
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(
        public readonly array $items = [],
        public readonly ?int $totalCount = null,
        public readonly ?bool $hasMore = null,
        /** @var array<string, mixed> */
        public readonly array $data = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $raw = $data['data'] ?? [];

        return new self(
            items: is_array($raw) ? array_values($raw) : [],
            totalCount: Cast::int($data['totalCount'] ?? null),
            hasMore: Cast::bool($data['hasMore'] ?? null),
            data: $data,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
