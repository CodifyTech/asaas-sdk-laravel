<?php

declare(strict_types=1);

namespace Asaas\Laravel\Tests\Services;

use Asaas\Laravel\AsaasServiceProvider;
use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Orchestra\Testbench\TestCase;

/** Shared setup and assertions for the generated per-service tests (tools/generator). */
abstract class ServiceTestCase extends TestCase
{
    protected const BASE_URL = 'https://api-sandbox.asaas.com';

    protected const PDF_ACCEPT = 'application/pdf, application/json';

    // Untyped $app on purpose: Testbench 9 through 11 declare these hooks without a parameter type.
    protected function getPackageProviders($app)
    {
        return [AsaasServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('asaas', [
            'api_key' => 'test_key',
            'environment' => 'sandbox',
            'retry' => ['max_retries' => 1],
        ]);
    }

    /** @param array<mixed>|string $body @param array<string, string> $headers */
    protected function fakeResponse(array|string $body = [], int $status = 200, array $headers = []): void
    {
        Http::fake(['*' => Http::response($body, $status, $headers)]);
    }

    /**
     * Asserts exactly one request with this method, path (already encoded), query and, when given, JSON body.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function assertRequestSent(string $method, string $path, array $query = [], ?array $body = null, string $accept = 'application/json'): void
    {
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === $method
            && $this->urlWithoutQuery($request) === self::BASE_URL.$path
            && $this->decodeQuery($request) === $this->normalizeQuery($query)
            && $request->hasHeader('Accept', $accept)
            // Compare the raw JSON body: it is exactly what the SDK put on the wire.
            && ($body === null || json_decode($request->body(), true) == $body));
    }

    /** @param list<string> $partNames */
    protected function assertMultipartSent(string $path, array $partNames): void
    {
        sort($partNames);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($path, $partNames) {
            $names = array_column($request->data(), 'name');
            sort($names);

            return $request->method() === 'POST'
                && $this->urlWithoutQuery($request) === self::BASE_URL.$path
                && $request->isMultipart()
                && $names === $partNames;
        });
    }

    /** Calls the closure, expects a ValidationException naming these keys, and no HTTP request. @param list<string> $errorKeys */
    protected function assertValidationRejected(Closure $call, array $errorKeys): void
    {
        Http::fake();
        try {
            $call();
        } catch (ValidationException $exception) {
            foreach ($errorKeys as $key) {
                $this->assertArrayHasKey($key, $exception->errors());
            }
            Http::assertNothingSent();

            return;
        }
        $this->fail('Expected ValidationException was not thrown.');
    }

    private function urlWithoutQuery(Request $request): string
    {
        return explode('?', $request->url(), 2)[0];
    }

    /** @return array<string, string> */
    private function decodeQuery(Request $request): array
    {
        $decoded = [];
        foreach (array_filter(explode('&', explode('?', $request->url(), 2)[1] ?? ''), 'strlen') as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $decoded[rawurldecode($key)] = rawurldecode($value);
        }
        ksort($decoded);

        return $decoded;
    }

    /** @param array<string, mixed> $query @return array<string, string> */
    private function normalizeQuery(array $query): array
    {
        $normalized = array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v, $query);
        ksort($normalized);

        return $normalized;
    }
}
