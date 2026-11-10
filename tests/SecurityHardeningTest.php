<?php

declare(strict_types=1);

namespace Asaas\Laravel\Tests;

use Asaas\Laravel\AsaasSdk;
use Asaas\Laravel\Config\ApiKeyAuthConfig;
use Asaas\Laravel\Config\AsaasSdkConfig;
use Asaas\Laravel\Config\RetryConfig;
use Asaas\Laravel\Environment;
use Asaas\Laravel\Exceptions\ApiError;
use Asaas\Laravel\Http\RequestBuilder;
use Asaas\Laravel\Models\Cast;
use Asaas\Laravel\Models\Customer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SecurityHardeningTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function unsafeBaseUrls(): iterable
    {
        yield 'plain http to a remote host' => ['http://api.asaas.com'];
        yield 'credentials in the URL' => ['https://user:secret@api.asaas.com'];
        yield 'query string' => ['https://api.asaas.com?x=1'];
        yield 'fragment' => ['https://api.asaas.com#frag'];
        yield 'no scheme' => ['api.asaas.com'];
        yield 'other scheme' => ['ftp://api.asaas.com'];
    }

    #[DataProvider('unsafeBaseUrls')]
    public function test_rejects_unsafe_base_urls(string $baseUrl): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AsaasSdkConfig::fromArray(['api_key' => 'k', 'base_url' => $baseUrl]);
    }

    public function test_accepts_https_and_loopback_http_base_urls(): void
    {
        $this->assertSame('https://proxy.example.com/asaas', AsaasSdkConfig::fromArray(['base_url' => 'https://proxy.example.com/asaas'])->getBaseUrl());
        $this->assertSame('http://localhost:8080', AsaasSdkConfig::fromArray(['base_url' => 'http://localhost:8080'])->getBaseUrl());
        $this->assertSame('http://127.0.0.1', AsaasSdkConfig::fromArray(['base_url' => 'http://127.0.0.1'])->getBaseUrl());
        $this->assertSame('https://api-sandbox.asaas.com', AsaasSdkConfig::fromArray(['base_url' => ''])->getBaseUrl());
    }

    public function test_rejects_unknown_environment_instead_of_defaulting(): void
    {
        $this->assertSame(Environment::Production, Environment::fromString(' PROD '));
        $this->assertSame(Environment::Sandbox, AsaasSdkConfig::fromArray([])->environment);

        $this->expectException(\InvalidArgumentException::class);
        Environment::fromString('producton');
    }

    public function test_rejects_non_positive_timeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AsaasSdkConfig::fromArray(['timeout' => 0]);
    }

    public function test_api_key_is_hidden_from_debug_output(): void
    {
        $config = AsaasSdkConfig::fromArray(['api_key' => '$aact_super_secret']);

        $this->assertStringNotContainsString('super_secret', print_r($config, true));
        $this->assertStringNotContainsString('super_secret', print_r($config->getApiKeyAuthConfig(), true));
        $this->assertStringNotContainsString('super_secret', print_r(new ApiKeyAuthConfig('super_secret'), true));
    }

    public function test_api_key_is_redacted_from_stack_traces(): void
    {
        $exception = null;
        try {
            AsaasSdk::fromConfig(['api_key' => 'super_secret', 'environment' => 'nope']);
        } catch (\InvalidArgumentException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertStringNotContainsString('super_secret', $exception->getTraceAsString());
    }

    /** @return iterable<string, array{string}> */
    public static function traversalIds(): iterable
    {
        yield 'parent directory' => ['..'];
        yield 'current directory' => ['.'];
        yield 'empty' => [''];
        yield 'blank' => ['   '];
    }

    #[DataProvider('traversalIds')]
    public function test_rejects_path_parameters_that_escape_the_endpoint(string $id): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new RequestBuilder('v3/customers/{id}'))->setPathParameter('id', $id);
    }

    public function test_path_parameters_are_encoded_as_a_single_segment(): void
    {
        $url = (new RequestBuilder('v3/customers/{id}'))->setPathParameter('id', '../payments')->buildUrl();

        $this->assertSame('v3/customers/..%2Fpayments', $url);
    }

    public function test_path_parameter_values_cannot_inject_placeholders(): void
    {
        $url = (new RequestBuilder('v3/a/{id}/b/{other}'))
            ->setPathParameter('id', '{other}')
            ->setPathParameter('other', 'x')
            ->buildUrl();

        $this->assertSame('v3/a/%7Bother%7D/b/x', $url);
    }

    public function test_missing_path_parameter_fails_before_sending(): void
    {
        $this->expectException(\LogicException::class);

        (new RequestBuilder('v3/customers/{id}'))->buildUrl();
    }

    public function test_rejects_header_injection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new RequestBuilder('v3/customers'))->setHeader('Accept', "application/json\r\nX-Evil: 1");
    }

    public function test_callers_cannot_override_the_api_key_or_user_agent(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $sdk = AsaasSdk::fromConfig(['api_key' => 'real_key', 'retry' => ['max_retries' => 0]]);
        $client = (new \ReflectionProperty($sdk->customer, 'httpClient'))->getValue($sdk->customer);

        $client->send('GET', 'v3/customers', ['access_token' => 'forged', 'User-Agent' => 'forged']);

        Http::assertSent(fn (Request $request) => $request->header('access_token') === ['real_key']
            && $request->header('User-Agent') === [AsaasSdkConfig::DEFAULT_USER_AGENT]);
    }

    public function test_error_messages_are_truncated(): void
    {
        Http::fake(['*' => Http::response(str_repeat('<html>', 1000), 502)]);
        $sdk = AsaasSdk::fromConfig(['api_key' => 'k', 'retry' => ['max_retries' => 0]]);

        try {
            $sdk->customer->get('cus_1');
            $this->fail('Expected ApiError.');
        } catch (ApiError $e) {
            $this->assertSame(502, $e->statusCode);
            $this->assertLessThanOrEqual(501, mb_strlen($e->getMessage()));
        }
    }

    public function test_retry_uses_exponential_backoff_capped_by_max_delay(): void
    {
        $retry = new RetryConfig(maxRetries: 5, initialDelay: 100, maxDelay: 500, backoffFactor: 2.0, jitter: 0);

        $this->assertSame([100, 200, 400, 500, 500], array_map($retry->delayFor(...), [1, 2, 3, 4, 5]));
        $this->assertSame(500, $retry->delayFor(1, retryAfterMs: 30_000));
        $this->assertSame(6, $retry->attempts());
    }

    public function test_retry_jitter_stays_within_bounds(): void
    {
        $retry = new RetryConfig(initialDelay: 100, maxDelay: 1000, jitter: 50);

        for ($i = 0; $i < 50; $i++) {
            $this->assertThat($retry->delayFor(1), $this->logicalAnd($this->greaterThanOrEqual(100), $this->lessThanOrEqual(150)));
        }
    }

    public function test_retry_config_rejects_unsafe_values(): void
    {
        foreach ([['maxRetries' => -1], ['maxRetries' => 11], ['initialDelay' => -1], ['backoffFactor' => 0.5]] as $args) {
            try {
                new RetryConfig(...$args);
                $this->fail('Expected InvalidArgumentException for '.json_encode($args));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_default_config_retries_get_once_with_backoff(): void
    {
        Sleep::fake();
        Http::fake(['*' => Http::sequence()
            ->push(['errors' => []], 503, ['Retry-After' => '0'])
            ->push(['data' => [], 'totalCount' => 0, 'hasMore' => false], 200)]);

        AsaasSdk::fromConfig(['api_key' => 'k'])->customer->list();

        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_redirects_are_not_followed(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://evil.example.com'])]);

        try {
            AsaasSdk::fromConfig(['api_key' => 'k', 'retry' => ['max_retries' => 0]])->customer->get('cus_1');
            $this->fail('Expected ApiError for a redirect.');
        } catch (ApiError $e) {
            $this->assertSame(302, $e->statusCode);
        }
        Http::assertSentCount(1);
    }

    public function test_models_tolerate_unexpected_payload_types(): void
    {
        $customer = Customer::fromArray(['id' => 123, 'name' => ['unexpected'], 'email' => null]);

        $this->assertSame('123', $customer->id);
        $this->assertNull($customer->name);
        $this->assertSame(['unexpected'], $customer->data['name']);

        $this->assertSame(10, Cast::int('10'));
        $this->assertNull(Cast::int('10.5'));
        $this->assertFalse(Cast::bool('false'));
        $this->assertNull(Cast::bool([]));
        $this->assertSame(29.9, Cast::float('29.9'));
        $this->assertNull(Cast::string(true));
    }
}
