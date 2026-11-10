<?php

namespace Asaas\Laravel\Models;

use Illuminate\Contracts\Support\Arrayable;

/** Resposta de listagem paginada de documentos da conta (API Asaas). */
final class AccountDocumentList implements Arrayable
{
    /** @param list<AccountDocument> $items */
    public function __construct(
        public readonly array $items = [],
        public readonly ?int $totalCount = null,
        public readonly ?bool $hasMore = null,
        public readonly ?string $rejectReasons = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $raw = $data['data'] ?? [];
        $items = [];
        foreach (is_array($raw) ? $raw : [] as $item) {
            $items[] = AccountDocument::fromArray(is_array($item) ? $item : []);
        }
        return new self(
            items: $items,
            totalCount: isset($data['totalCount']) ? (int) $data['totalCount'] : null,
            hasMore: $data['hasMore'] ?? null,
            rejectReasons: $data['rejectReasons'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'data' => array_map(fn (AccountDocument $x) => $x->toArray(), $this->items),
            'totalCount' => $this->totalCount,
            'hasMore' => $this->hasMore,
            'rejectReasons' => $this->rejectReasons,
        ];
    }
}
