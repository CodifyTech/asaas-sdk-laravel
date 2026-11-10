<?php

declare(strict_types=1);

namespace AsaasTools;

/** Emits the named new models (item = id + data, list = items + totalCount + hasMore), same shape as Customer/CustomerList. */
final class ModelEmitter
{
    public function __construct(private Plan $plan) {}

    /** @param list<string> $services models used by these services @return array<string, string> */
    public function emit(array $services): array
    {
        $used = [];
        foreach ($services as $service) {
            foreach ($this->plan->services[$service] as $i) {
                $used[$this->plan->entries[$i]['responseModel']] = true;
            }
        }
        $files = [];
        foreach (MapValidator::GENERATED_MODELS as $item) {
            if (isset($used[$item]) || isset($used[$item.'List'])) {
                $files["src/Models/$item.php"] = $this->item($item);
                $files["src/Models/{$item}List.php"] = $this->list($item);
            }
        }

        return $files;
    }

    private function item(string $name): string
    {
        return Php::file([
            ...$this->head(),
            '/**',
            " * Raw `{$name}` payload from the Asaas API.",
            ' *',
            ' * @implements Arrayable<string, mixed>',
            ' */',
            "final class $name implements Arrayable",
            '{',
            '    public function __construct(',
            '        public readonly ?string $id = null,',
            '        /** @var array<string, mixed> */',
            '        public readonly array $data = [],',
            '    ) {',
            '    }',
            '',
            '    /** @param array<string, mixed> $data */',
            '    public static function fromArray(array $data): self',
            '    {',
            "        return new self(id: Cast::string(\$data['id'] ?? null), data: \$data);",
            '    }',
            '',
            '    /** @return array<string, mixed> */',
            '    public function toArray(): array',
            '    {',
            "        return array_merge(\$this->data, array_filter(['id' => \$this->id], fn (\$v) => \$v !== null));",
            '    }',
            '}',
        ]);
    }

    private function list(string $item): string
    {
        $key = $this->listKey($item.'List');

        return Php::file([
            ...$this->head(),
            '/**',
            " * Paginated list of `{$item}` (API Asaas).",
            ' *',
            ' * @implements Arrayable<string, mixed>',
            ' */',
            "final class {$item}List implements Arrayable",
            '{',
            "    /** @param list<$item> \$items */",
            '    public function __construct(',
            '        public readonly array $items = [],',
            '        public readonly ?int $totalCount = null,',
            '        public readonly ?bool $hasMore = null,',
            '    ) {',
            '    }',
            '',
            '    /** @param array<string, mixed> $data */',
            '    public static function fromArray(array $data): self',
            '    {',
            "        \$raw = \$data['$key'] ?? [];",
            '        $items = [];',
            '        foreach (is_array($raw) ? $raw : [] as $item) {',
            "            \$items[] = $item::fromArray(is_array(\$item) ? \$item : []);",
            '        }',
            '        return new self(',
            '            items: $items,',
            "            totalCount: Cast::int(\$data['totalCount'] ?? null),",
            "            hasMore: Cast::bool(\$data['hasMore'] ?? null),",
            '        );',
            '    }',
            '',
            '    /** @return array<string, mixed> */',
            '    public function toArray(): array',
            '    {',
            '        return [',
            "            '$key' => array_map(fn (\$item) => \$item->toArray(), \$this->items),",
            "            'totalCount' => \$this->totalCount,",
            "            'hasMore' => \$this->hasMore,",
            '        ];',
            '    }',
            '}',
        ]);
    }

    /** @return list<string> */
    private function head(): array
    {
        return [...Php::phpHeader(), 'namespace Asaas\\Laravel\\Models;', '', 'use Illuminate\\Contracts\\Support\\Arrayable;', ''];
    }

    /** The array property of the list response schema (`data`, or `accessTokens` for subaccount tokens). */
    private function listKey(string $model): string
    {
        foreach ($this->plan->entries as $entry) {
            if ($entry['responseModel'] !== $model) {
                continue;
            }
            $content = $this->plan->spec->successContent($entry['op']);
            $schema = $this->plan->spec->resolve($content['application/json']['schema'] ?? []);
            foreach ($schema['properties'] ?? [] as $name => $prop) {
                if ($this->plan->rules->kind($this->plan->spec->resolve($prop)) === 'array') {
                    return $name;
                }
            }
        }
        throw new \RuntimeException("No list key found for $model");
    }
}
