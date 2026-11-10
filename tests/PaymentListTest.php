<?php

declare(strict_types=1);

namespace Asaas\Laravel\Tests;

use Asaas\Laravel\Models\Payment;
use Asaas\Laravel\Models\PaymentList;
use PHPUnit\Framework\TestCase;

class PaymentListTest extends TestCase
{
    public function test_from_array_maps_data_to_payment_instances(): void
    {
        $payload = [
            'data' => [
                ['id' => 'pay_1', 'customer' => 'cus_1', 'value' => 100.0, 'status' => 'PENDING'],
                ['id' => 'pay_2', 'customer' => 'cus_2', 'value' => 200.0, 'status' => 'RECEIVED'],
            ],
            'totalCount' => 2,
            'hasMore' => false,
        ];

        $list = PaymentList::fromArray($payload);

        $this->assertInstanceOf(PaymentList::class, $list);
        $this->assertCount(2, $list->items);
        $this->assertInstanceOf(Payment::class, $list->items[0]);
        $this->assertInstanceOf(Payment::class, $list->items[1]);
        $this->assertSame('pay_1', $list->items[0]->id);
        $this->assertSame(100.0, $list->items[0]->value);
        $this->assertSame('pay_2', $list->items[1]->id);
        $this->assertSame(200.0, $list->items[1]->value);
        $this->assertSame(2, $list->totalCount);
        $this->assertFalse($list->hasMore);
    }

    public function test_from_array_handles_empty_data(): void
    {
        $payload = [
            'data' => [],
            'totalCount' => 0,
            'hasMore' => false,
        ];

        $list = PaymentList::fromArray($payload);

        $this->assertCount(0, $list->items);
        $this->assertSame(0, $list->totalCount);
        $this->assertFalse($list->hasMore);
    }

    public function test_from_array_handles_missing_data_key(): void
    {
        $payload = ['totalCount' => 0, 'hasMore' => false];

        $list = PaymentList::fromArray($payload);

        $this->assertCount(0, $list->items);
        $this->assertSame(0, $list->totalCount);
    }

    public function test_from_array_handles_null_total_count_and_has_more(): void
    {
        $payload = ['data' => []];

        $list = PaymentList::fromArray($payload);

        $this->assertNull($list->totalCount);
        $this->assertNull($list->hasMore);
    }
}
