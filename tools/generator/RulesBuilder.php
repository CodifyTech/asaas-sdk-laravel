<?php

declare(strict_types=1);

namespace AsaasTools;

/**
 * Maps OpenAPI schemas to Laravel validation rules.
 *
 * A field is ['key', 'rules' (strings or Raw), 'required' (yes|with|no), 'type', 'description', 'enum', 'format'].
 */
final class RulesBuilder
{
    private const MAX_DEPTH = 3;

    public function __construct(private Spec $spec) {}

    /** @param array<string, mixed> $schema resolved object schema @return list<array<string, mixed>> */
    public function forBody(array $schema): array
    {
        $fields = [];
        $this->walk($schema, '', 1, 'required', $fields);

        return $fields;
    }

    /**
     * @param  list<array<string, mixed>>  $params
     * @param  bool  $paginated  adds offset/limit when the page does not declare them (paginated responses)
     * @return list<array<string, mixed>>
     */
    public function forQuery(array $params, bool $paginated = false): array
    {
        $fields = [];
        $names = array_column($params, 'name');
        if ($paginated) {
            $missing = [
                'offset' => ['Elemento inicial da lista', 'integer'],
                'limit' => ['Número de elementos da lista (máx.: 100)', 'integer'],
            ];
            foreach ($missing as $name => [$description, $type]) {
                if (! in_array($name, $names, true)) {
                    $field = $this->paginationBounds($this->field($name, ['type' => $type], 'nullable', $description));
                    $fields[] = $field + ['synthetic' => true];
                }
            }
        }
        foreach ($params as $param) {
            $required = (bool) ($param['required'] ?? false);
            $fields[] = $this->paginationBounds($this->field($param['name'], $param['schema'] ?? [], $required ? 'required' : 'nullable', $param['description'] ?? ''));
        }

        return $fields;
    }

    /**
     * Pagination contract (https://docs.asaas.com/reference/listagem-e-paginacao): offset starts at 0, limit is 1 to 100.
     * The per-operation specs omit the minimums, so they are applied to every offset/limit query parameter.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function paginationBounds(array $field): array
    {
        $bounds = ['offset' => ['min:0'], 'limit' => ['min:1', 'max:100']];
        if (! isset($bounds[$field['key']]) || $field['type'] !== 'integer') {
            return $field;
        }
        $kept = array_filter($field['rules'], fn ($r) => ! (is_string($r) && (str_starts_with($r, 'min:') || str_starts_with($r, 'max:'))));
        $field['rules'] = [...$kept, ...$bounds[$field['key']]];

        return $field;
    }

    /** @param list<array<string, mixed>> $fields */
    public static function render(array $fields): string
    {
        $lines = [];
        foreach ($fields as $field) {
            $rules = array_map(fn ($r) => Php::export($r), $field['rules']);
            $lines[] = '            '.Php::export($field['key']).' => ['.implode(', ', $rules).'],';
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $schema @param list<array<string, mixed>> $out */
    private function walk(array $schema, string $prefix, int $level, string $requiredRule, array &$out): void
    {
        $required = $schema['required'] ?? [];
        // Official text: when creditCardToken is sent, creditCard and creditCardHolderInfo are not required.
        $tokenAlternative = $prefix === '' && isset($schema['properties']['creditCardToken']);
        foreach ($schema['properties'] ?? [] as $name => $prop) {
            $key = $prefix.$name;
            $rule = in_array($name, $required, true) ? $requiredRule : 'nullable';
            if ($tokenAlternative && in_array($name, ['creditCard', 'creditCardHolderInfo'], true) && $rule === 'required') {
                $rule = 'required_without:creditCardToken';
            }
            $out[] = $this->field($key, $prop, $rule, $prop['description'] ?? '');
            $resolved = $this->spec->resolve($prop);
            if ($level >= self::MAX_DEPTH) {
                continue;
            }
            if ($this->kind($resolved) === 'object') {
                $this->walk($resolved, $key.'.', $level + 1, 'required_with:'.$key, $out);
            } elseif ($this->kind($resolved) === 'array') {
                $this->walkItems($resolved, $key, $level, $out);
            }
        }
    }

    /** @param array<string, mixed> $array @param list<array<string, mixed>> $out */
    private function walkItems(array $array, string $key, int $level, array &$out): void
    {
        $items = $this->spec->resolve($array['items'] ?? []);
        if ($this->kind($items) === 'object') {
            $this->walk($items, $key.'.*.', $level + 1, 'required', $out);
        } elseif ($items !== []) {
            $out[] = $this->field($key.'.*', $items, null, '');
        }
    }

    /** @param array<string, mixed> $schema */
    private function field(string $key, array $schema, ?string $presence, string $description): array
    {
        $resolved = $this->spec->resolve($schema);
        $description = $description !== '' ? $description : (string) ($resolved['description'] ?? '');
        $rules = $presence === null ? [] : [$presence];
        $type = $this->kind($resolved);
        $format = $resolved['format'] ?? null;
        // An enum on an array describes its items (spec quirk), so it is applied to `key.*` only.
        $enum = in_array($type, ['array', 'object'], true) ? null : ($resolved['enum'] ?? null);

        $rules[] = match (true) {
            $type === 'string' && $format === 'binary' => new Raw('self::file()'),
            $type === 'string' && $format === 'date' => 'date_format:Y-m-d',
            $type === 'string' && $format === 'date-time' => 'date',
            default => ['string' => 'string', 'integer' => 'integer', 'number' => 'numeric', 'boolean' => 'boolean'][$type] ?? 'array',
        };
        if (is_array($enum) && $enum !== []) {
            $values = array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v, $enum);
            foreach ($values as $value) {
                if (str_contains($value, ',')) {
                    throw new \RuntimeException("Enum value with a comma at $key: $value");
                }
            }
            $rules[] = 'in:'.implode(',', $values);
        }
        foreach (['minLength' => 'min', 'minimum' => 'min', 'maxLength' => 'max', 'maximum' => 'max'] as $keyword => $rule) {
            if (isset($resolved[$keyword])) {
                $rules[] = $rule.':'.$resolved[$keyword];
            }
        }

        return [
            'key' => $key,
            'rules' => $rules,
            'required' => match (true) {
                $presence === 'required' => 'yes',
                is_string($presence) && str_starts_with($presence, 'required_without') => 'without',
                is_string($presence) && str_starts_with($presence, 'required_with') => 'with',
                default => 'no',
            },
            'type' => $format === 'binary' ? 'file' : $type,
            'format' => $format,
            'enum' => $enum,
            'description' => $description,
        ];
    }

    /** @param array<string, mixed> $schema */
    public function kind(array $schema): string
    {
        $type = $schema['type'] ?? null;
        if ($type === null) {
            return isset($schema['properties']) ? 'object' : (isset($schema['items']) ? 'array' : 'string');
        }

        return (string) $type;
    }
}
