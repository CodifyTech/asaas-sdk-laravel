<?php

declare(strict_types=1);

namespace Asaas\Laravel\Tests;

use Asaas\Laravel\Http\Requests\Payment\ListPaymentsRequest;
use Illuminate\Validation\ValidationException;
use Orchestra\Testbench\TestCase;

class ListPaymentsRequestTest extends TestCase
{
    public function test_validate_accepts_valid_params(): void
    {
        $data = [
            'offset' => 0,
            'limit' => 10,
            'customer' => 'cus_abc',
            'billingType' => 'PIX',
            'status' => 'PENDING',
        ];

        $result = ListPaymentsRequest::validate($data);

        $this->assertSame(0, $result['offset']);
        $this->assertSame(10, $result['limit']);
        $this->assertSame('cus_abc', $result['customer']);
        $this->assertSame('PIX', $result['billingType']);
        $this->assertSame('PENDING', $result['status']);
    }

    public function test_validate_accepts_empty_array(): void
    {
        $result = ListPaymentsRequest::validate([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_validate_accepts_only_offset_and_limit(): void
    {
        $result = ListPaymentsRequest::validate(['offset' => 5, 'limit' => 50]);

        $this->assertSame(5, $result['offset']);
        $this->assertSame(50, $result['limit']);
    }

    public function test_validate_rejects_invalid_billing_type(): void
    {
        $this->expectException(ValidationException::class);

        ListPaymentsRequest::validate(['billingType' => 'INVALID_TYPE']);
    }

    public function test_validate_rejects_limit_below_min(): void
    {
        $this->expectException(ValidationException::class);

        ListPaymentsRequest::validate(['limit' => 0]);
    }

    public function test_validate_rejects_limit_above_max(): void
    {
        $this->expectException(ValidationException::class);

        ListPaymentsRequest::validate(['limit' => 101]);
    }

    public function test_validate_rejects_negative_offset(): void
    {
        $this->expectException(ValidationException::class);

        ListPaymentsRequest::validate(['offset' => -1]);
    }
}
