<?php

namespace AsaasTools;

/** Builds sample inputs and responses from schemas, for generated tests and docs. */
final class Sample
{
    private const DATE = '2026-01-31';
    private const DATE_TIME = '2026-01-31 10:00:00';

    /** Lowercase "<sdkMethod> <path>" of the operation being sampled, for context-dependent values. */
    private string $context = '';

    public function __construct(private Spec $spec, private RulesBuilder $rules)
    {
    }

    /** @param array<string, mixed> $entry */
    public function forEntry(array $entry): self
    {
        $copy = clone $this;
        $copy->context = strtolower($entry['sdkMethod'] . ' ' . $entry['path']);
        return $copy;
    }

    /** Minimal valid body: required properties only (plus children required by them). @return array<string, mixed> */
    public function body(array $schema, int $depth = 0): array
    {
        $values = [];
        $required = $schema['required'] ?? [];
        foreach ($schema['properties'] ?? [] as $name => $prop) {
            if (in_array($name, $required, true)) {
                $values[$name] = $this->value($prop, $depth, $name);
            }
        }
        return $values;
    }

    /** Multipart sample: the required body plus every top-level file field. @return array<string, mixed> */
    public function multipartBody(array $schema): array
    {
        $values = $this->body($schema);
        foreach ($schema['properties'] ?? [] as $name => $prop) {
            $value = isset($values[$name]) ? null : $this->value($prop, 0, $name);
            if ($value instanceof Raw || (is_array($value) && ($value[0] ?? null) instanceof Raw)) {
                $values[$name] = $value;
            }
        }
        return $values;
    }

    /** Query sample: offset, limit and the first bracket filter when present. @return array<string, mixed> */
    public function query(array $params): array
    {
        $picked = [];
        $bracket = false;
        foreach ($params as $param) {
            $name = $param['name'];
            $isBracket = str_contains($name, '[');
            if (in_array($name, ['offset', 'limit'], true) || ($isBracket && ! $bracket)) {
                $picked[$name] = $this->value($param['schema'] ?? [], 0, $name);
                $bracket = $bracket || $isBracket;
            }
        }
        if ($picked === [] && $params !== []) {
            $picked[$params[0]['name']] = $this->value($params[0]['schema'] ?? [], 0, $params[0]['name']);
        }
        return $picked;
    }

    /** Response body with a bounded size. */
    public function response(array $schema, int $depth = 0, string $name = ''): mixed
    {
        $schema = $this->spec->resolve($schema);
        $kind = $this->rules->kind($schema);
        if ($kind === 'array') {
            return $depth > 2 ? [] : [$this->response($schema['items'] ?? [], $depth + 1, $name)];
        }
        if ($kind !== 'object' || ! isset($schema['properties'])) {
            return $this->value($schema, $depth, $name);
        }
        $limit = [10, 6, 4][$depth] ?? 0;
        $names = array_keys($schema['properties']);
        usort($names, fn ($a, $b) => (int) in_array($b, ['id', 'data']) <=> (int) in_array($a, ['id', 'data']));
        $values = [];
        foreach (array_slice($names, 0, $depth > 2 ? 0 : $limit) as $key) {
            $values[$key] = $this->response($schema['properties'][$key], $depth + 1, $key);
        }
        return $values;
    }

    /** @param array<string, mixed> $schema */
    public function value(array $schema, int $depth, string $name = ''): mixed
    {
        $schema = $this->spec->resolve($schema);
        $kind = $this->rules->kind($schema);
        if (($schema['format'] ?? null) === 'binary') {
            return new Raw(self::fileExpression($name));
        }
        if (isset($schema['enum'][0]) && ! in_array($kind, ['array', 'object'], true)) {
            return $this->enumValue($schema['enum'], $name);
        }
        $example = Spec::example($schema);
        return match ($kind) {
            'object' => $depth > 3 ? ['value' => 'x'] : ($this->body($schema, $depth + 1) ?: $this->firstProperty($schema, $depth)),
            'array' => [$this->value($schema['items'] ?? [], $depth + 1, $name)],
            'integer' => (int) $this->clampNumber($schema, $example, 1),
            'number' => (float) $this->clampNumber($schema, $example, 1.5),
            'boolean' => is_bool($example) ? $example : true,
            default => $this->text($schema, $example, $name),
        };
    }

    /** File fields get a realistic file name: the docs example turns it into a path. */
    public static function fileExpression(string $field): string
    {
        $file = match (true) {
            str_contains(strtolower($field), 'certificate') => 'certificate.pfx',
            (bool) preg_match('/image|logo/i', $field) => 'image.png',
            default => 'document.pdf',
        };
        return "UploadedFile::fake()->create('$file', 10)";
    }

    private function enumValue(array $values, string $name): mixed
    {
        if ($name === 'billingType' && in_array('CREDIT_CARD', $values, true) && str_contains($this->context, 'creditcard')) {
            return 'CREDIT_CARD';
        }
        return $values[0];
    }

    /** @return array<string, mixed> */
    private function firstProperty(array $schema, int $depth): array
    {
        foreach ($schema['properties'] ?? [] as $name => $prop) {
            return [$name => $this->value($prop, $depth + 1, $name)];
        }
        return ['value' => 'x'];
    }

    private function clampNumber(array $schema, mixed $example, int|float $default): int|float
    {
        $number = is_numeric($example) ? $example + 0 : $default;
        if (isset($schema['maximum']) && $number > $schema['maximum']) {
            $number = $schema['maximum'];
        }
        if (isset($schema['minimum']) && $number < $schema['minimum']) {
            $number = $schema['minimum'];
        }
        return $number;
    }

    private function text(array $schema, mixed $example, string $name): string
    {
        $format = $schema['format'] ?? null;
        if ($format === 'date' || ($format === null && str_contains(strtolower($name), 'date'))) {
            return self::DATE;
        }
        if ($format === 'date-time') {
            return self::DATE_TIME;
        }
        // Non-ASCII examples (Portuguese prose) stay out of the generated PHP.
        $ascii = is_string($example) && $example !== '' && mb_check_encoding($example, 'ASCII');
        $text = $this->byName($name) ?? ($ascii ? $example : 'test_' . (preg_replace('/\W+/', '_', $name) ?: 'value'));
        if (isset($schema['maxLength'])) {
            $text = substr($text, 0, (int) $schema['maxLength']);
        }
        return isset($schema['minLength']) ? str_pad($text, (int) $schema['minLength'], 'x') : $text;
    }

    /** Realistic values for field names whose meaning is obvious. */
    private function byName(string $name): ?string
    {
        $lower = strtolower($name);
        return match (true) {
            $lower === 'remoteip' => '203.0.113.10',
            str_contains($lower, 'phone') => '11999999999',
            str_contains($lower, 'cpfcnpj') => '24971563792',
            str_contains($lower, 'email') => 'john.doe@example.com',
            str_contains($lower, 'postalcode') => '89223005',
            str_contains($lower, 'token') => 'a1b2c3d4-0000-4000-8000-000000000001',
            $lower === 'customer' => 'cus_000005219613',
            $lower === 'payment' => 'pay_080225913252',
            $lower === 'walletid' => '7bafd95a-e783-4a62-9be1-23999af742c6',
            str_ends_with($lower, 'url') => 'https://example.com/callback',
            $lower === 'description' => 'Test description',
            $lower === 'name' || str_ends_with($lower, 'name') => 'John Doe',
            default => null,
        };
    }
}
