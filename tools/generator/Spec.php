<?php

declare(strict_types=1);

namespace AsaasTools;

/** Read-only view over the OpenAPI snapshot (tools/asaas-openapi.json). */
final class Spec
{
    public const VERBS = ['get', 'post', 'put', 'delete', 'patch'];

    /** @var array<string, mixed> */
    public array $data;

    public function __construct(string $file)
    {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (! is_array($decoded)) {
            throw new \RuntimeException("Invalid spec JSON: $file");
        }
        $this->data = $decoded;
    }

    /** Follows $ref chains. @param array<string, mixed> $schema @return array<string, mixed> */
    public function resolve(array $schema): array
    {
        for ($i = 0; isset($schema['$ref']); $i++) {
            $name = substr((string) $schema['$ref'], strrpos((string) $schema['$ref'], '/') + 1);
            if ($i > 20 || ! isset($this->data['components']['schemas'][$name])) {
                throw new \RuntimeException("Unresolvable \$ref {$schema['$ref']}");
            }
            $schema = $this->data['components']['schemas'][$name];
        }

        return $schema;
    }

    /** @return array<string, mixed>|null */
    public function op(string $method, string $path): ?array
    {
        return $this->data['paths'][$path][strtolower($method)] ?? null;
    }

    /** @return list<string> "METHOD path" keys of every operation */
    public function operationKeys(): array
    {
        $keys = [];
        foreach ($this->data['paths'] as $path => $items) {
            foreach ($items as $verb => $unused) {
                if (in_array($verb, self::VERBS, true)) {
                    $keys[] = strtoupper($verb).' '.$path;
                }
            }
        }

        return $keys;
    }

    /** @return array{0: ?string, 1: array<string, mixed>} content type and resolved body schema */
    public function body(array $op): array
    {
        $content = $op['requestBody']['content'] ?? [];
        $type = array_key_first($content);

        return $type === null ? [null, []] : [$type, $this->resolve($content[$type]['schema'] ?? [])];
    }

    /** @return list<array<string, mixed>> */
    public function queryParams(array $op): array
    {
        return array_values(array_filter($op['parameters'] ?? [], fn ($p) => ($p['in'] ?? '') === 'query'));
    }

    /** @return list<string> */
    public function pathParams(array $op): array
    {
        $names = [];
        foreach ($op['parameters'] ?? [] as $param) {
            if (($param['in'] ?? '') === 'path') {
                $names[] = $param['name'];
            }
        }

        return $names;
    }

    /** @return array<string, array<string, mixed>> content type => response object of the 2xx response */
    public function successContent(array $op): array
    {
        foreach ($op['responses'] ?? [] as $code => $response) {
            if (str_starts_with((string) $code, '2')) {
                return $response['content'] ?? [];
            }
        }

        return [];
    }

    /** Paginated response: a top-level `data` array plus `hasMore` or `totalCount`. */
    public function isPaginated(array $op): bool
    {
        $schema = $this->resolve($this->successContent($op)['application/json']['schema'] ?? []);
        $props = $schema['properties'] ?? [];

        return isset($props['data']) && ($this->resolve($props['data'])['type'] ?? null) === 'array'
            && (isset($props['hasMore']) || isset($props['totalCount']));
    }

    /** Spec examples can be null or the wrong type, so only scalars count. */
    public static function example(array $schema): mixed
    {
        $example = $schema['example'] ?? null;

        return is_scalar($example) ? $example : null;
    }
}
