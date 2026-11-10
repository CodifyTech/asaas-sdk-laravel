<?php

declare(strict_types=1);

namespace AsaasTools;

/** Emits tests/Services/<Service>Test.php (see tests/Services/ServiceTestCase.php for the assertions). */
final class TestEmitter
{
    private const PDF_SAMPLE = '%PDF-1.4 sample';

    public function __construct(private Plan $plan) {}

    /** @param list<string> $services @return array<string, string> */
    public function emit(array $services): array
    {
        $files = [];
        foreach ($services as $service) {
            $files["tests/Services/{$service}Test.php"] = $this->render($service);
        }

        return $files;
    }

    private function render(string $service): string
    {
        $imports = [];
        $tests = [];
        foreach ($this->plan->services[$service] as $i) {
            $entry = $this->plan->entries[$i];
            if (! in_array($entry['responseModel'], ['string', 'void'], true)) {
                $imports[] = 'Asaas\\Laravel\\Models\\'.$entry['responseModel'];
            }
            if ($entry['multipart']) {
                $imports[] = 'Illuminate\\Http\\UploadedFile';
            }
            $tests[] = $this->operationTest($entry);
            if ($this->ownsValidationTest($i, $entry)) {
                $tests[] = $this->validationTest($entry);
                array_push($tests, ...$this->tokenAlternativeTests($entry));
            }
        }
        $imports = array_values(array_unique($imports));
        sort($imports);

        $lines = Php::phpHeader();
        $lines[] = 'namespace Asaas\\Laravel\\Tests\\Services;';
        $lines[] = '';
        foreach ($imports as $import) {
            $lines[] = "use $import;";
        }
        $lines[] = '';
        $lines[] = "class {$service}Test extends ServiceTestCase";
        $lines[] = '{';
        $lines[] = implode("\n\n", $tests);
        $lines[] = '}';

        return Php::file($lines);
    }

    /** One validation test per request class: emitted for the first operation that owns the class rules. */
    private function ownsValidationTest(int $index, array $entry): bool
    {
        $class = $entry['requestClass'];

        return $class !== null
            && ($this->plan->classes[$class]['entry'] ?? null) === $index
            && $this->plan->requiredKeys($class) !== [];
    }

    /** @param array<string, mixed> $entry */
    private function operationTest(array $entry, string $suffix = '', ?array $body = null): string
    {
        $model = $entry['responseModel'];
        $path = $this->samplePath($entry);
        $args = $this->callArgs($entry, $body);
        $call = "asaas()->{$entry['property']}->{$entry['sdkMethod']}(".implode(', ', $args['code']).')';

        $lines = ['    public function test_'.Php::snake($entry['sdkMethod']).$suffix.'(): void', '    {'];
        $lines[] = '        '.$this->fake($entry);
        $lines[] = '';
        $lines[] = $model === 'void' ? "        $call;" : "        \$result = $call;";
        $lines[] = '';
        $lines[] = '        '.$this->assertSent($entry, $path, $args);
        if ($model === 'string') {
            $lines[] = "        \$this->assertSame('".self::PDF_SAMPLE."', \$result);";
        } elseif ($model !== 'void') {
            $lines[] = "        \$this->assertInstanceOf($model::class, \$result);";
        }
        $lines[] = '    }';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $entry */
    private function fake(array $entry): string
    {
        return match ($entry['responseModel']) {
            'void' => "\$this->fakeResponse('', 204);",
            'string' => "\$this->fakeResponse('".self::PDF_SAMPLE."', 200, ['Content-Type' => 'application/pdf']);",
            default => '$this->fakeResponse('.Php::export($this->responseSample($entry)).');',
        };
    }

    /** @param array<string, mixed> $entry */
    private function responseSample(array $entry): mixed
    {
        $content = $this->plan->spec->successContent($entry['op']);
        $sample = $this->plan->sample->forEntry($entry)->response($content['application/json']['schema'] ?? []);

        return is_array($sample) ? $sample : [];
    }

    /** @param array<string, mixed> $entry */
    private function assertSent(array $entry, string $path, array $args): string
    {
        $method = Php::export($entry['method']);
        $path = Php::export($path);
        if ($entry['multipart']) {
            $names = Php::export(array_keys($args['body']));

            return "\$this->assertMultipartSent($path, $names);";
        }
        $body = in_array($entry['method'], ['POST', 'PUT', 'PATCH'], true) ? $args['body'] : null;
        $accept = $entry['responseModel'] === 'string' ? 'self::PDF_ACCEPT' : null;
        $extra = [Php::export($args['query']), Php::export($body)];
        if ($accept !== null) {
            $extra[] = $accept;
        }
        // Drop trailing defaults to keep the call short.
        while ($accept === null && $extra !== [] && in_array(end($extra), ['[]', 'null'], true)) {
            array_pop($extra);
        }

        return "\$this->assertRequestSent($method, $path".($extra ? ', '.implode(', ', $extra) : '').');';
    }

    /** @param array<string, mixed> $entry @return array{code: list<string>, body: array<string, mixed>, query: array<string, mixed>} */
    private function callArgs(array $entry, ?array $bodyOverride = null): array
    {
        $code = array_map(fn ($name) => Php::export($this->pathValue($name)), $entry['pathParams']);
        $body = [];
        $query = [];
        if ($entry['hasBody']) {
            $body = $bodyOverride ?? $this->plan->sampleBody($entry);
            $code[] = Php::export($body);
        }
        if ($entry['hasParams']) {
            $query = $this->plan->sampleQuery($entry);
            $code[] = Php::export($query);
        }

        return ['code' => $code, 'body' => $body, 'query' => $query];
    }

    /** @param array<string, mixed> $entry */
    private function validationTest(array $entry): string
    {
        $class = $entry['requestClass'];
        $args = array_map(fn ($name) => Php::export($this->pathValue($name)), $entry['pathParams']);
        if ($entry['hasBody']) {
            $args[] = '[]';
        }
        $call = "asaas()->{$entry['property']}->{$entry['sdkMethod']}(".implode(', ', $args).')';
        $keys = Php::export($this->plan->requiredKeys($class));

        return implode("\n", [
            '    public function test_'.Php::snake($entry['sdkMethod']).'_rejects_missing_required_fields(): void',
            '    {',
            "        \$this->assertValidationRejected(fn () => $call, $keys);",
            '    }',
        ]);
    }

    /**
     * For bodies where creditCardToken replaces creditCard/creditCardHolderInfo: one call with only the token must
     * be sent, one with neither must be rejected.
     *
     * @param  array<string, mixed>  $entry  @return list<string>
     */
    private function tokenAlternativeTests(array $entry): array
    {
        $alternatives = [];
        foreach ($this->plan->classes[$entry['requestClass']]['fields'] as $field) {
            if (str_contains(implode(',', array_filter($field['rules'], 'is_string')), 'required_without:creditCardToken')) {
                $alternatives[] = $field['key'];
            }
        }
        if ($alternatives === []) {
            return [];
        }
        $base = array_diff_key($this->plan->sampleBody($entry), array_flip($alternatives));
        $withToken = $base + ['creditCardToken' => $this->plan->sample->forEntry($entry)->value(['type' => 'string'], 0, 'creditCardToken')];
        $name = Php::snake($entry['sdkMethod']);
        $args = array_map(fn ($n) => Php::export($this->pathValue($n)), $entry['pathParams']);
        $neither = "asaas()->{$entry['property']}->{$entry['sdkMethod']}(".implode(', ', [...$args, Php::export($base)]).')';

        return [
            $this->operationTest($entry, '_accepts_token_instead_of_card_fields', $withToken),
            implode("\n", [
                "    public function test_{$name}_rejects_neither_card_fields_nor_token(): void",
                '    {',
                "        \$this->assertValidationRejected(fn () => $neither, ".Php::export($alternatives).');',
                '    }',
            ]),
        ];
    }

    private function pathValue(string $name): string
    {
        return $name === 'phoneNumber' ? '11999999999' : $name.'_1';
    }

    /** @param array<string, mixed> $entry */
    private function samplePath(array $entry): string
    {
        $path = $entry['path'];
        foreach ($entry['pathParams'] as $name) {
            $path = str_replace('{'.$name.'}', rawurlencode($this->pathValue($name)), $path);
        }

        return $path;
    }
}
