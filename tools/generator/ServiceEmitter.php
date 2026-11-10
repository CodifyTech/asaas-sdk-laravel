<?php

namespace AsaasTools;

/** Emits src/Services/<Service>.php and the request classes. */
final class ServiceEmitter
{
    private const NS_REQUESTS = 'Asaas\\Laravel\\Http\\Requests\\';
    private const NS_MODELS = 'Asaas\\Laravel\\Models\\';
    private const DOCS_URL = 'https://docs.asaas.com/reference/';
    private const PDF_ACCEPT = 'application/pdf, application/json';

    public function __construct(private Plan $plan)
    {
    }

    /** @param list<string> $services @return array<string, string> */
    public function emit(array $services): array
    {
        $files = [];
        foreach ($services as $service) {
            $files["src/Services/$service.php"] = $this->service($service, $this->plan->services[$service]);
        }
        return $files;
    }

    /** @param list<int> $indexes */
    private function service(string $service, array $indexes): string
    {
        $imports = [
            'Asaas\\Laravel\\Exceptions\\ApiError',
            'Asaas\\Laravel\\Http\\RequestBuilder',
        ];
        $methods = [];
        foreach ($indexes as $i) {
            $entry = $this->plan->entries[$i];
            if ($entry['requestClass'] !== null) {
                $imports[] = self::NS_REQUESTS . $entry['requestClass'];
                $imports[] = 'Illuminate\\Validation\\ValidationException';
            }
            if (! in_array($entry['responseModel'], ['string', 'void'], true)) {
                $imports[] = self::NS_MODELS . $entry['responseModel'];
            }
            if ($entry['barePagination']) {
                $imports[] = self::NS_REQUESTS . Plan::PAGINATION_CLASS;
                $imports[] = 'Illuminate\\Validation\\ValidationException';
            }
            $methods[] = $this->method($entry);
        }
        $imports = array_values(array_unique($imports));
        sort($imports);
        $this->assertNoShortNameClash($service, $imports);

        $lines = Php::phpHeader();
        $lines[] = 'namespace Asaas\\Laravel\\Services;';
        $lines[] = '';
        foreach ($imports as $import) {
            $lines[] = "use $import;";
        }
        $lines[] = '';
        $lines[] = "class $service extends BaseService";
        $lines[] = '{';
        $lines[] = implode("\n\n", $methods);
        $lines[] = '}';
        return Php::file($lines);
    }

    /** @param list<string> $imports */
    private function assertNoShortNameClash(string $service, array $imports): void
    {
        $short = array_map([Php::class, 'shortName'], $imports);
        if (count($short) !== count(array_unique($short))) {
            throw new \RuntimeException("Short class name clash in $service: " . implode(', ', $imports));
        }
    }

    /** @param array<string, mixed> $entry */
    private function method(array $entry): string
    {
        $class = $entry['requestClass'] === null ? null : Php::shortName($entry['requestClass']);
        $hasParams = $entry['hasParams'];

        $args = array_map(fn ($name) => "string \$$name", $entry['pathParams']);
        $docs = ['@see ' . self::DOCS_URL . $entry['docSlug']];
        if (isset($entry['docblockNote'])) {
            array_unshift($docs, $entry['docblockNote'], '');
        }
        if ($entry['hasBody']) {
            $args[] = $entry['dataRequired'] ? 'array $data' : 'array $data = []';
            $docs[] = '@param array<string, mixed> $data';
        }
        if ($hasParams) {
            $args[] = 'array $params = []';
            $docs[] = '@param array<string, mixed> $params';
        }
        $docs[] = $class === null && ! $hasParams ? '@throws ApiError' : '@throws ApiError|ValidationException';

        $model = $entry['responseModel'];
        $lines = ['    /**'];
        foreach ($docs as $doc) {
            $lines[] = rtrim('     * ' . $doc);
        }
        $lines[] = '     */';
        $lines[] = "    public function {$entry['sdkMethod']}(" . implode(', ', $args) . "): $model";
        $lines[] = '    {';
        array_push($lines, ...$this->builder($entry, $class, $hasParams));
        array_push($lines, ...$this->tail($entry, $model));
        $lines[] = '    }';
        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $entry @return list<string> */
    private function builder(array $entry, ?string $class, bool $hasParams): array
    {
        $path = ltrim($entry['path'], '/');
        $new = "new RequestBuilder('$path', '{$entry['method']}')";
        $chain = [];
        foreach ($entry['pathParams'] as $name) {
            $chain[] = "->setPathParameter('$name', \$$name)";
        }
        $lines = [];
        if ($hasParams) {
            // Paginated lists that document no query accept only the pagination keys.
            $queryClass = $class ?? Php::shortName(Plan::PAGINATION_CLASS);
            $chain[] = "->setQueryParameters($queryClass::validateQuery(\$params))";
        }
        if ($entry['hasBody']) {
            $setter = $entry['multipart'] ? 'setMultipartContent' : 'setJsonContent';
            $chain[] = "->$setter($class::validate(\$data))";
        }
        if ($entry['responseModel'] === 'string') {
            $chain[] = "->setHeader('Accept', '" . self::PDF_ACCEPT . "')";
        }
        if ($chain === []) {
            return [...$lines, "        \$builder = $new;"];
        }
        $lines[] = "        \$builder = ($new)";
        foreach ($chain as $n => $call) {
            $lines[] = '            ' . $call . ($n === array_key_last($chain) ? ';' : '');
        }
        return $lines;
    }

    /** @param array<string, mixed> $entry @return list<string> */
    private function tail(array $entry, string $model): array
    {
        return match ($model) {
            'string' => ['        return $this->execute($this->buildRequest($builder))->body();'],
            'void' => ['        $this->execute($this->buildRequest($builder));'],
            default => [
                "        /** @var $model \$result */",
                "        \$result = \$this->executeAndMap(\$this->buildRequest(\$builder), $model::class);",
                '        return $result;',
            ],
        };
    }
}
