<?php

declare(strict_types=1);

namespace AsaasTools;

/** Emits src/Http/Requests/<Dir>/<Name>.php: one class per unique input, rules() only. */
final class RequestEmitter
{
    public function __construct(private Plan $plan) {}

    /** @param list<string> $services only classes used by these services @return array<string, string> */
    public function emit(array $services): array
    {
        $wanted = [];
        foreach ($services as $service) {
            foreach ($this->plan->services[$service] as $i) {
                $entry = $this->plan->entries[$i];
                $wanted[$entry['barePagination'] ? Plan::PAGINATION_CLASS : (string) $entry['requestClass']] = true;
            }
        }
        $files = [];
        if (isset($wanted[Plan::PAGINATION_CLASS])) {
            $files['src/Http/Requests/Common/PaginationRequest.php'] = $this->pagination();
        }
        foreach ($this->plan->classes as $class => $info) {
            if (isset($wanted[$class])) {
                $files['src/Http/Requests/'.str_replace('\\', '/', $class).'.php'] = $this->render($class, $info);
            }
        }

        return $files;
    }

    private function pagination(): string
    {
        $fields = $this->plan->paginationFields();

        return Php::file([
            ...Php::phpHeader(),
            'namespace Asaas\\Laravel\\Http\\Requests\\Common;',
            '',
            'use Asaas\\Laravel\\Http\\Requests\\AsaasRequest;',
            '',
            '/** @internal Pagination-only query shared by list operations that document no filters. */',
            'final class PaginationRequest extends AsaasRequest',
            '{',
            '    /** @return array<string, array<int, mixed>> */',
            '    public static function rules(): array',
            '    {',
            '        return [',
            RulesBuilder::render($fields),
            '        ];',
            '    }',
            '}',
        ]);
    }

    /** @param array{fields: list<array<string, mixed>>, entry: int, kind: string} $info */
    private function render(string $class, array $info): string
    {
        [$dir, $name] = explode('\\', $class);
        $entry = $this->plan->entries[$info['entry']];
        $lines = Php::phpHeader();
        $lines[] = "namespace Asaas\\Laravel\\Http\\Requests\\$dir;";
        $lines[] = '';
        $lines[] = 'use Asaas\\Laravel\\Http\\Requests\\AsaasRequest;';
        $lines[] = '';
        $lines[] = "/** @internal Validation rules for {$entry['method']} {$entry['path']}. */";
        $lines[] = "final class $name extends AsaasRequest";
        $lines[] = '{';
        $lines[] = '    /** @return array<string, array<int, mixed>> */';
        $lines[] = '    public static function rules(): array';
        $lines[] = '    {';
        $lines[] = '        return [';
        $lines[] = RulesBuilder::render($info['fields']);
        $lines[] = '        ];';
        $lines[] = '    }';
        $lines[] = '}';

        return Php::file($lines);
    }
}
