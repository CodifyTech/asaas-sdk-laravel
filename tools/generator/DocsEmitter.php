<?php

namespace AsaasTools;

/** Emits docs/services/<Service>.md (PT-BR) and docs/README-services.md. */
final class DocsEmitter
{
    private const DOCS_URL = 'https://docs.asaas.com/reference/';

    public function __construct(private Plan $plan)
    {
    }

    /** @param list<string> $services @return array<string, string> */
    public function emit(array $services, bool $withIndex): array
    {
        $files = [];
        foreach ($services as $service) {
            $files["docs/services/$service.md"] = $this->service($service);
        }
        if ($withIndex) {
            $files['docs/README-services.md'] = $this->index();
        }
        return $files;
    }

    private function header(): string
    {
        return '<!-- ' . Php::HEADER . ' -->';
    }

    private function service(string $service): string
    {
        $indexes = $this->plan->services[$service];
        $first = $this->plan->entries[$indexes[0]];
        $lines = [$this->header(), '', "# $service", '', '## Propósito', ''];
        $lines[] = "Operações da categoria **{$first['category']}** da API Asaas v3 (" . count($indexes) . ' endpoints).';
        $lines = [...$lines, '', '## Acesso', '', '```php', "\$service = asaas()->{$first['property']};", '```', ''];
        $lines = [...$lines, '## Métodos', '', '| Método | HTTP | Caminho | Parâmetros | Retorno |', '|---|---|---|---|---|'];
        foreach ($indexes as $i) {
            $e = $this->plan->entries[$i];
            $lines[] = "| `{$e['sdkMethod']}` | {$e['method']} | `{$e['path']}` | " . $this->paramsCell($e) . ' | `' . $e['responseModel'] . '` |';
        }
        $lines = [...$lines, '', '## Detalhes', ''];
        foreach ($indexes as $i) {
            array_push($lines, ...$this->details($this->plan->entries[$i]));
        }
        $lines = [...$lines, '## Exemplo', '', '```php'];
        foreach ($indexes as $i) {
            $lines[] = $this->exampleLine($this->plan->entries[$i]);
        }
        $lines[] = '```';
        return Php::file($lines);
    }

    /** @param array<string, mixed> $e */
    private function paramsCell(array $e): string
    {
        $cells = array_map(fn ($name) => "`string \$$name`", $e['pathParams']);
        if ($e['hasBody']) {
            $cells[] = $e['dataRequired'] ? '`array $data`' : '`array $data = []`';
        }
        if ($e['hasParams']) {
            $cells[] = '`array $params = []`';
        }
        return $cells === [] ? '-' : implode(', ', $cells);
    }

    /** @param array<string, mixed> $e @return list<string> */
    private function details(array $e): array
    {
        $lines = ["### `{$e['sdkMethod']}`: {$e['title']}", '', self::linkLine($e), ''];
        if ($e['multipart']) {
            $lines = [...$lines, 'Envio `multipart/form-data`: arquivos devem ser `\SplFileInfo` ou stream.', ''];
        }
        if ($e['responseModel'] === 'string') {
            $lines = [...$lines, 'Retorna o PDF bruto (`string`).', ''];
        }
        if (isset($e['docsNote'])) {
            $lines = [...$lines, '> ' . $e['docsNote'], ''];
        }
        $class = $e['requestClass'] ?? ($e['barePagination'] ? Plan::PAGINATION_CLASS : null);
        if ($class === null) {
            return $lines;
        }
        $fields = $this->plan->classes[$class]['fields'] ?? $this->plan->paginationFields();
        $lines[] = $e['hasQuery'] ? 'Parâmetros de consulta validados (chaves desconhecidas são rejeitadas):' : 'Campos do corpo validados (campos extras são enviados sem validação):';
        $lines = [...$lines, '', '| Campo | Tipo | Obrigatório | Restrições | Descrição |', '|---|---|---|---|---|'];
        foreach ($fields as $f) {
            $lines[] = "| `{$f['key']}` | " . $this->typeCell($f) . ' | ' . $this->requiredCell($f) . ' | ' . $this->constraints($f) . ' | ' . $this->clean($f['description']) . ' |';
        }
        $lines[] = '';
        return $lines;
    }

    /** @param array<string, mixed> $e */
    private static function linkLine(array $e): string
    {
        return 'Documentação oficial: <' . self::DOCS_URL . $e['docSlug'] . '>';
    }

    /** @param array<string, mixed> $f */
    private function typeCell(array $f): string
    {
        $names = ['string' => 'texto', 'integer' => 'inteiro', 'number' => 'número', 'boolean' => 'booleano', 'object' => 'objeto', 'array' => 'lista', 'file' => 'arquivo'];
        $type = $names[$f['type']] ?? $f['type'];
        return match ($f['format']) {
            'date' => 'data (AAAA-MM-DD)',
            'date-time' => 'data e hora',
            default => $type,
        };
    }

    /** @param array<string, mixed> $f */
    private function requiredCell(array $f): string
    {
        foreach ($f['rules'] as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'required_with:')) {
                return 'se `' . substr($rule, 14) . '` informado';
            }
        }
        foreach ($f['rules'] as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'required_without:')) {
                return 'se `' . substr($rule, 17) . '` não informado';
            }
        }
        return $f['required'] === 'yes' ? 'Sim' : 'Não';
    }

    /** @param array<string, mixed> $f */
    private function constraints(array $f): string
    {
        $parts = [];
        foreach ($f['rules'] as $rule) {
            if (! is_string($rule)) {
                continue;
            }
            $fromGuide = ($f['synthetic'] ?? false) || in_array($f['key'], ['offset', 'limit'], true);
            if (str_starts_with($rule, 'in:')) {
                $parts[] = 'valores: ' . implode(', ', array_map(fn ($v) => "`$v`", explode(',', substr($rule, 3))));
            } elseif (str_starts_with($rule, 'max:')) {
                $parts[] = 'máximo ' . substr($rule, 4) . (($f['synthetic'] ?? false) ? ' (guia de paginação)' : '');
            } elseif (str_starts_with($rule, 'min:')) {
                $parts[] = 'mínimo ' . substr($rule, 4) . ($fromGuide ? ' (guia de paginação)' : '');
            }
        }
        return $parts === [] ? '-' : implode('; ', $parts);
    }

    private function clean(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
        $text = str_replace('|', '\\|', $text);
        return $text === '' ? '-' : $text;
    }

    /** @param array<string, mixed> $e */
    private function exampleLine(array $e): string
    {
        $args = array_map(fn ($name) => Php::export($name === 'phoneNumber' ? '11999999999' : $name . '_1'), $e['pathParams']);
        if ($e['hasBody']) {
            $args[] = $this->docArray($this->plan->sampleBody($e));
        }
        if ($e['hasParams']) {
            $args[] = $this->docArray($this->plan->sampleQuery($e));
        }
        $result = $e['responseModel'] === 'void' ? '' : '$result = ';
        return "// {$e['title']}\n$result" . "asaas()->{$e['property']}->{$e['sdkMethod']}(" . implode(', ', $args) . ');';
    }

    /** @param array<string, mixed> $values */
    private function docArray(array $values): string
    {
        return str_replace("UploadedFile::fake()->create('document.pdf', 10)", "new \\SplFileInfo('/path/to/document.pdf')", Php::export($values));
    }

    private function index(): string
    {
        $lines = [$this->header(), '', '# Serviços', '', 'Cada serviço é uma propriedade de `AsaasSdk` (por exemplo `asaas()->customer`) e espelha uma categoria da [referência oficial](https://docs.asaas.com/reference).', ''];
        $lines = [...$lines, '| Propriedade | Serviço | Categoria | Operações |', '|---|---|---|---|'];
        $total = 0;
        foreach ($this->plan->services as $service => $indexes) {
            $first = $this->plan->entries[$indexes[0]];
            $total += count($indexes);
            $lines[] = "| `{$first['property']}` | [$service](services/$service.md) | {$first['category']} | " . count($indexes) . ' |';
        }
        return Php::file([...$lines, '', "**Total:** " . count($this->plan->services) . " serviços, $total operações."]);
    }
}
