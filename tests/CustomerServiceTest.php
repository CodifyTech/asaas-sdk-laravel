<?php

declare(strict_types=1);

namespace Asaas\Laravel\Tests;

use Asaas\Laravel\Config\AsaasSdkConfig;
use Asaas\Laravel\Http\AsaasHttpClient;
use Asaas\Laravel\Models\Customer;
use Asaas\Laravel\Services\CustomerService;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class CustomerServiceTest extends TestCase
{
    public function test_create_accepts_array_and_does_not_require_request_type(): void
    {
        $payload = [
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'cpfCnpj' => '12345678909',
        ];
        $apiResponseBody = [
            'id' => 'cus_abc123',
            'name' => $payload['name'],
            'email' => $payload['email'],
            'cpfCnpj' => $payload['cpfCnpj'],
        ];

        Http::fake([
            '*' => Http::response($apiResponseBody, 200, ['Content-Type' => 'application/json']),
        ]);

        $config = AsaasSdkConfig::fromArray([
            'api_key' => 'key_test',
            'environment' => 'sandbox',
        ]);
        $httpClient = new AsaasHttpClient($config);
        $service = new CustomerService($httpClient, $config);

        $result = $service->create($payload);

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertSame('cus_abc123', $result->id);
        $this->assertSame($payload['name'], $result->name);
        $this->assertSame($payload['email'], $result->email);
        $this->assertSame($payload['cpfCnpj'], $result->cpfCnpj);
    }
}
