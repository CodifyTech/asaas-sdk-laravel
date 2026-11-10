<?php

declare(strict_types=1);

namespace Asaas\Laravel\Tests;

use Asaas\Laravel\AsaasServiceProvider;
use Asaas\Laravel\Exceptions\ApiError;
use Asaas\Laravel\Exceptions\ErrorResponseDtoException;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

// End-to-end retry behaviour through the container-bound SDK.
class RetryTest extends TestCase
{
    // Untyped $app on purpose: Testbench 9 through 11 declare these hooks without a parameter type.
    protected function getPackageProviders($app)
    {
        return [AsaasServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('asaas', [
            'api_key' => 'k',
            'environment' => 'production',
            'retry' => ['max_retries' => 3, 'initial_delay' => 1],
        ]);
    }

    public function test_retries_retryable_status_then_succeeds(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['errors' => []], 503)
            ->push(['data' => [], 'totalCount' => 0, 'hasMore' => false], 200)]);

        asaas()->customer->list();

        Http::assertSentCount(2);
    }

    public function test_does_not_retry_non_retryable_status(): void
    {
        Http::fake(['*' => Http::response(['errors' => [['code' => 'x', 'description' => 'y']]], 400)]);

        try {
            asaas()->customer->list();
            $this->fail('Expected ErrorResponseDtoException for HTTP 400.');
        } catch (ErrorResponseDtoException $e) {
            $this->assertSame(400, $e->statusCode);
        }

        Http::assertSentCount(1);
    }

    public function test_post_is_not_retried_by_default(): void
    {
        Http::fake(['*' => Http::response(['errors' => []], 503)]);

        try {
            asaas()->customer->create(['name' => 'N', 'cpfCnpj' => '12345678909']);
            $this->fail('Expected ApiError for HTTP 503.');
        } catch (ApiError $e) {
            $this->assertSame(503, $e->statusCode);
        }

        Http::assertSentCount(1);
    }
}
