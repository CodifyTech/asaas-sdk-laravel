<?php

declare(strict_types=1);

namespace Asaas\Laravel\Tests;

use Asaas\Laravel\AsaasSdk;
use Asaas\Laravel\AsaasServiceProvider;
use Asaas\Laravel\Exceptions\ApiError;
use Asaas\Laravel\Exceptions\ErrorResponseDtoException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class AsaasServiceProviderTest extends TestCase
{
    private const API_KEY = 'key_prod';

    private const USER_AGENT = 'provider-test-agent/9.9';

    // Untyped $app on purpose: Testbench 9 through 11 declare these hooks without a parameter type.
    protected function getPackageProviders($app)
    {
        return [AsaasServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('asaas', [
            'api_key' => self::API_KEY,
            'environment' => 'production',
            'user_agent' => self::USER_AGENT,
            'retry' => ['max_retries' => 1],
        ]);
    }

    public function test_container_resolves_sdk_as_singleton_and_helper_returns_same_instance(): void
    {
        $sdk = $this->app->make(AsaasSdk::class);

        $this->assertSame($sdk, $this->app->make(AsaasSdk::class));
        $this->assertSame($sdk, asaas());
    }

    public function test_production_environment_sends_authenticated_request_to_production_host(): void
    {
        $this->fakeEmptyCustomerList();

        asaas()->customer->list();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.asaas.com/v3/customers'
            && $request->hasHeader('access_token', self::API_KEY)
            && $request->hasHeader('User-Agent', self::USER_AGENT));
    }

    public function test_sandbox_environment_sends_request_to_sandbox_host(): void
    {
        $this->app['config']->set('asaas.environment', 'sandbox');
        $this->fakeEmptyCustomerList();

        asaas()->customer->list();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api-sandbox.asaas.com/v3/customers');
    }

    public function test_missing_environment_config_defaults_to_sandbox_host(): void
    {
        $this->app['config']->set('asaas', ['api_key' => self::API_KEY]);
        $this->fakeEmptyCustomerList();

        asaas()->customer->list();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api-sandbox.asaas.com/v3/customers');
    }

    public function test_bad_request_throws_error_response_dto_exception_exposing_api_errors(): void
    {
        $errors = [['code' => 'invalid_x', 'description' => 'msg']];
        Http::fake(['*' => Http::response(['errors' => $errors], 400)]);

        $exception = $this->captureApiError();

        $this->assertInstanceOf(ErrorResponseDtoException::class, $exception);
        $this->assertSame($errors, $exception->getErrors());
    }

    public function test_not_found_throws_plain_api_error_with_status_code(): void
    {
        Http::fake(['*' => Http::response(['errors' => [['code' => 'not_found', 'description' => 'msg']]], 404)]);

        $exception = $this->captureApiError();

        $this->assertSame(ApiError::class, $exception::class);
        $this->assertSame(404, $exception->statusCode);
    }

    private function fakeEmptyCustomerList(): void
    {
        Http::fake(['*' => Http::response(['data' => [], 'totalCount' => 0, 'hasMore' => false])]);
    }

    private function captureApiError(): ApiError
    {
        try {
            asaas()->customer->list();
        } catch (ApiError $exception) {
            return $exception;
        }

        $this->fail('Expected ApiError was not thrown.');
    }
}
