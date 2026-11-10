<?php

namespace AsaasTools;

/** Map entries enriched with spec data: rules, shared request classes, samples. */
final class Plan
{
    /** Shared rules (offset, limit) for list methods whose operation documents no query. */
    public const PAGINATION_CLASS = 'Common\\PaginationRequest';

    /** @var list<array<string, mixed>> */
    public array $entries = [];
    /** @var array<string, array{fields: list<array<string, mixed>>, entry: int, kind: string}> */
    public array $classes = [];
    /** @var array<string, list<int>> service => entry indexes */
    public array $services = [];
    /** @var list<string> */
    public array $errors = [];

    public RulesBuilder $rules;
    public Sample $sample;

    /** @param list<array<string, mixed>> $map */
    public function __construct(array $map, public Spec $spec)
    {
        $this->rules = new RulesBuilder($spec);
        $this->sample = new Sample($spec, $this->rules);
        foreach ($map as $i => $entry) {
            $this->entries[$i] = $this->enrich($entry);
            $this->services[$entry['service']][] = $i;
        }
        foreach ($this->entries as $i => $entry) {
            if (! isset($entry['rulesFrom'])) {
                $this->registerClass($i, $entry);
            }
        }
        foreach ($this->entries as $i => $entry) {
            if (isset($entry['rulesFrom'])) {
                $this->entries[$i] = $this->copyRules($entry);
                $this->registerClass($i, $this->entries[$i]);
            }
        }
        foreach (array_keys($this->entries) as $i) {
            $entry = $this->entries[$i];
            $this->entries[$i]['dataRequired'] = $entry['hasBody'] && $this->requiredKeys($entry['requestClass']) !== [];
        }
    }

    /** Minimal valid body for the entry (required fields; files for multipart). @param array<string, mixed> $entry @return array<string, mixed> */
    public function sampleBody(array $entry): array
    {
        $owner = $this->entries[$this->classes[$entry['requestClass']]['entry']];
        $sample = $this->sample->forEntry($entry);
        return $entry['multipart'] ? $sample->multipartBody($owner['bodySchema']) : $sample->body($owner['bodySchema']);
    }

    /** Rules shared by paginated lists that document no query: offset and limit. @return list<array<string, mixed>> */
    public function paginationFields(): array
    {
        return $this->rules->forQuery([], true);
    }

    /** Sample query: pagination keys always present for paginated lists. @param array<string, mixed> $entry @return array<string, mixed> */
    public function sampleQuery(array $entry): array
    {
        $query = $this->sample->forEntry($entry)->query($entry['queryParams']);
        if ($entry['paginated']) {
            $query = $query + ['offset' => 0, 'limit' => 10];
        }
        return $query;
    }

    /** @return list<string> top-level required field names of a request class */
    public function requiredKeys(?string $class): array
    {
        $keys = [];
        foreach ($this->classes[$class]['fields'] ?? [] as $field) {
            if (! str_contains($field['key'], '.') && in_array($field['required'], ['yes', 'without'], true)) {
                $keys[] = $field['key'];
            }
        }
        return $keys;
    }

    /** @param array<string, mixed> $entry @return array<string, mixed> */
    private function enrich(array $entry): array
    {
        $op = $this->spec->op($entry['method'], $entry['path']) ?? [];
        [, $body] = $this->spec->body($op);
        $query = $this->spec->queryParams($op);
        $fields = [];
        if ($entry['requestClass'] !== null && ! isset($entry['rulesFrom'])) {
            $fields = $entry['hasQuery'] ? $this->rules->forQuery($query, $entry['paginated']) : $this->rules->forBody($body);
        }
        $entry['hasParams'] = $entry['hasQuery'] || $entry['paginated'];
        $entry['barePagination'] = $entry['paginated'] && ! $entry['hasQuery'];
        $entry['op'] = $op;
        $entry['fields'] = $fields;
        $entry['bodySchema'] = $body;
        $entry['queryParams'] = $query;
        $entry['title'] = (string) ($op['summary'] ?? $entry['sdkMethod']);
        return $entry;
    }

    /**
     * Entries with `rulesFrom` reuse another class's rules and body sample (spec bug: empty body); `allOptional`
     * turns its top-level required rules into nullable.
     *
     * @param array<string, mixed> $entry @return array<string, mixed>
     */
    private function copyRules(array $entry): array
    {
        $source = $this->classes[$entry['rulesFrom']] ?? null;
        if ($source === null) {
            $this->errors[] = "rulesFrom {$entry['rulesFrom']} is not a request class with rules";
            return $entry;
        }
        $fields = $source['fields'];
        if ($entry['allOptional'] ?? false) {
            foreach ($fields as $n => $field) {
                if (! str_contains($field['key'], '.') && $field['required'] === 'yes') {
                    $field['rules'][0] = 'nullable';
                    $field['required'] = 'no';
                    $fields[$n] = $field;
                }
            }
        }
        $entry['fields'] = $fields;
        $entry['bodySchema'] = $this->entries[$source['entry']]['bodySchema'];
        return $entry;
    }

    /** @param array<string, mixed> $entry */
    private function registerClass(int $index, array $entry): void
    {
        $class = $entry['requestClass'];
        if ($class === null || $entry['fields'] === []) {
            return;
        }
        $existing = $this->classes[$class] ?? null;
        if ($existing === null) {
            $this->classes[$class] = ['fields' => $entry['fields'], 'entry' => $index, 'kind' => $entry['hasQuery'] ? 'query' : 'body'];
        } elseif (RulesBuilder::render($existing['fields']) !== RulesBuilder::render($entry['fields'])) {
            $this->errors[] = "requestClass $class shared by different inputs: {$entry['method']} {$entry['path']}";
        }
    }
}
