<?php

namespace Asaas\Laravel\Tests;

use Asaas\Laravel\Config\AsaasSdkConfig;
use Asaas\Laravel\Environment;
use Asaas\Laravel\Http\RequestBuilder;
use PHPUnit\Framework\TestCase;

class RequestBuilderTest extends TestCase
{
    public function test_build_url_with_path_params(): void
    {
        $builder = new RequestBuilder('v3/customers/{id}', 'GET');
        $builder->setPathParameter('id', 'cus_123');
        $built = $builder->build();
        $this->assertSame('GET', $built['method']);
        $this->assertStringContainsString('cus_123', $built['path']);
        $this->assertSame('v3/customers/cus_123', $built['path']);
    }

    public function test_build_url_with_query_params(): void
    {
        $builder = new RequestBuilder('v3/customers', 'GET');
        $builder->setOptionalQueryParameter('limit', 10);
        $builder->setOptionalQueryParameter('offset', 0);
        $built = $builder->build();
        $this->assertStringContainsString('limit=10', $built['path']);
        $this->assertStringContainsString('offset=0', $built['path']);
    }

    public function test_path_params_are_percent_encoded(): void
    {
        $built = (new RequestBuilder('v3/things/{id}', 'GET'))->setPathParameter('id', 'a/b c?d')->build();
        $this->assertSame('v3/things/a%2Fb%20c%3Fd', $built['path']);
    }

    public function test_set_query_parameters_skips_nulls_and_writes_booleans_as_words(): void
    {
        $built = (new RequestBuilder('v3/things'))
            ->setQueryParameters(['a' => true, 'b' => false, 'c' => null, 'd' => 0])
            ->build();
        $this->assertSame('v3/things?a=true&b=false&d=0', $built['path']);
    }

    public function test_bracket_query_keys_are_encoded(): void
    {
        $built = (new RequestBuilder('v3/payments'))->setQueryParameter('paymentDate[ge]', '2026-01-01')->build();
        $this->assertSame('v3/payments?paymentDate%5Bge%5D=2026-01-01', $built['path']);
    }

    public function test_build_flags_multipart_only_when_multipart_content_is_set(): void
    {
        $json = (new RequestBuilder('v3/a', 'POST'))->setJsonContent(['x' => 1])->build();
        $multipart = (new RequestBuilder('v3/a', 'POST'))->setMultipartContent(['x' => 1])->build();

        $this->assertFalse($json['multipart']);
        $this->assertTrue($multipart['multipart']);
        $this->assertSame(['x' => 1], $multipart['body']);
    }

    public function test_config_from_array(): void
    {
        $config = AsaasSdkConfig::fromArray([
            'api_key' => 'key_xxx',
            'environment' => 'sandbox',
        ]);
        $this->assertSame('key_xxx', $config->apiKey);
        $this->assertSame(Environment::Sandbox, $config->environment);
        $this->assertStringContainsString('sandbox', $config->getBaseUrl());
    }
}
