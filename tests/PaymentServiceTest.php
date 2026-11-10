<?php

declare(strict_types=1);

namespace Asaas\Laravel\Tests;

use Asaas\Laravel\Config\AsaasSdkConfig;
use Asaas\Laravel\Http\AsaasHttpClient;
use Asaas\Laravel\Models\Payment;
use Asaas\Laravel\Models\PaymentList;
use Asaas\Laravel\Services\PaymentService;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class PaymentServiceTest extends TestCase
{
    public function test_create_accepts_array_and_returns_payment(): void
    {
        $payload = [
            'customer' => 'cus_abc123',
            'value' => 100.00,
            'dueDate' => '2025-03-15',
            'billingType' => 'BOLETO',
        ];
        $apiResponseBody = [
            'id' => 'pay_xyz789',
            'customer' => $payload['customer'],
            'value' => $payload['value'],
            'status' => 'PENDING',
        ];

        Http::fake([
            '*' => Http::response($apiResponseBody, 200, ['Content-Type' => 'application/json']),
        ]);

        $config = AsaasSdkConfig::fromArray([
            'api_key' => 'key_test',
            'environment' => 'sandbox',
        ]);
        $httpClient = new AsaasHttpClient($config);
        $service = new PaymentService($httpClient, $config);

        $result = $service->create($payload);

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertSame('pay_xyz789', $result->id);
        $this->assertSame($payload['customer'], $result->customer);
        $this->assertSame(100.0, $result->value);
        $this->assertSame('PENDING', $result->status);
    }

    public function test_list_returns_payment_list_and_uses_validated_params(): void
    {
        $apiResponseBody = [
            'data' => [
                ['id' => 'pay_1', 'customer' => 'cus_1', 'value' => 50.0, 'status' => 'PENDING'],
            ],
            'totalCount' => 1,
            'hasMore' => false,
        ];

        Http::fake([
            '*' => Http::response($apiResponseBody, 200, ['Content-Type' => 'application/json']),
        ]);

        $config = AsaasSdkConfig::fromArray([
            'api_key' => 'key_test',
            'environment' => 'sandbox',
        ]);
        $httpClient = new AsaasHttpClient($config);
        $service = new PaymentService($httpClient, $config);

        $result = $service->list(['limit' => 10, 'offset' => 0]);

        $this->assertInstanceOf(PaymentList::class, $result);
        $this->assertCount(1, $result->items);
        $this->assertInstanceOf(Payment::class, $result->items[0]);
        $this->assertSame('pay_1', $result->items[0]->id);
        $this->assertSame(1, $result->totalCount);
        $this->assertFalse($result->hasMore);
    }
}
