<?php

namespace AsaasTools;

/** Emits resources/boost/skills/asaas-sdk-laravel/references/<property>.md: compact English method references for AI agents. */
final class ReferenceEmitter
{
    public const DIR = 'resources/boost/skills/asaas-sdk-laravel/references';
    private const DOCS_URL = 'https://docs.asaas.com/reference/';
    private const SERVICE_NS = 'Asaas\\Laravel\\Services\\';

    public function __construct(private Plan $plan)
    {
    }

    /** @param list<string> $services @return array<string, string> */
    public function emit(array $services): array
    {
        $files = [];
        foreach ($services as $service) {
            $indexes = $this->plan->services[$service];
            $property = $this->plan->entries[$indexes[0]]['property'];
            $files[self::DIR . "/$property.md"] = $this->service($service, $property, $indexes);
        }
        return $files;
    }

    /** @param list<int> $indexes */
    private function service(string $service, string $property, array $indexes): string
    {
        $lines = ['<!-- ' . Php::HEADER . ' -->', '', "# asaas()->$property", '', 'Class `' . self::SERVICE_NS . "$service`. Bodies and queries are arrays; unknown query keys are rejected.", ''];
        foreach ($indexes as $i) {
            array_push($lines, ...$this->method($this->plan->entries[$i]));
        }
        return Php::file($lines);
    }

    /** @param array<string, mixed> $e @return list<string> */
    private function method(array $e): array
    {
        $args = array_map(fn ($name) => "string \$$name", $e['pathParams']);
        if ($e['hasBody']) {
            $args[] = $e['dataRequired'] ? 'array $data' : 'array $data = []';
        }
        if ($e['hasParams']) {
            $args[] = 'array $params = []';
        }
        $lines = ["## `{$e['sdkMethod']}(" . implode(', ', $args) . "): {$e['responseModel']}`", "{$e['method']} {$e['path']}"];
        $fields = $e['requestClass'] === null ? [] : ($this->plan->classes[$e['requestClass']]['fields'] ?? []);
        if ($e['barePagination']) {
            $fields = $this->plan->paginationFields();
        }
        if ($e['hasParams']) {
            $lines[] = 'query: ' . implode(', ', array_column($fields, 'key'));
        } elseif ($e['hasBody']) {
            array_push($lines, ...$this->bodyLines($fields));
        }
        if ($e['multipart']) {
            $lines[] = 'multipart';
        }
        if ($e['responseModel'] === 'string') {
            $lines[] = 'returns raw PDF';
        }
        $lines[] = 'docs: ' . self::DOCS_URL . $e['docSlug'];
        $lines[] = '';
        return $lines;
    }

    /** @param list<array<string, mixed>> $fields @return list<string> */
    private function bodyLines(array $fields): array
    {
        $required = $withParent = [];
        foreach ($fields as $f) {
            $rule = '';
            foreach ($f['rules'] as $r) {
                if (is_string($r) && str_starts_with($r, 'required_without:')) {
                    $rule = substr($r, 17);
                }
            }
            if ($f['required'] === 'yes' && ! str_contains($f['key'], '.')) {
                $required[] = $f['key'];
            } elseif ($f['required'] === 'without') {
                $required[] = $f['key'] . " (or $rule)";
            } elseif (in_array($f['required'], ['yes', 'with'], true)) {
                $withParent[] = $f['key'];
            }
        }
        $lines = [];
        if ($required !== []) {
            $lines[] = 'required: ' . implode(', ', $required);
        }
        if ($withParent !== []) {
            $lines[] = 'required if parent sent: ' . implode(', ', $withParent);
        }
        return $lines;
    }
}
