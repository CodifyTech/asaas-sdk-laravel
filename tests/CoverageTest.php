<?php

namespace Asaas\Laravel\Tests;

use Asaas\Laravel\AsaasSdk;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/** Turns "213 of 213 Asaas operations" into a test: every map entry must exist in the SDK with its signature. */
class CoverageTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function map(): array
    {
        $map = json_decode((string) file_get_contents(__DIR__ . '/../tools/endpoint-map.json'), true);
        $this->assertIsArray($map);

        return $map;
    }

    public function test_map_covers_all_official_operations(): void
    {
        $keys = array_map(fn (array $e) => $e['method'] . ' ' . $e['path'], $this->map());

        $this->assertCount(213, $keys);
        $this->assertCount(213, array_unique($keys));
    }

    public function test_every_operation_is_exposed_by_the_sdk_with_the_mapped_signature(): void
    {
        $sdk = new ReflectionClass(AsaasSdk::class);
        $failures = [];

        foreach ($this->map() as $entry) {
            $label = $entry['service'] . '::' . $entry['sdkMethod'];
            $failure = $this->signatureFailure($sdk, $entry);
            if ($failure !== null) {
                $failures[] = "$label: $failure";
            }
        }

        $this->assertSame([], $failures);
    }

    /** @param array<string, mixed> $entry */
    private function signatureFailure(ReflectionClass $sdk, array $entry): ?string
    {
        $serviceClass = 'Asaas\\Laravel\\Services\\' . $entry['service'];
        if (! $sdk->hasProperty($entry['property'])) {
            return "AsaasSdk has no property {$entry['property']}";
        }
        $propertyType = $sdk->getProperty($entry['property'])->getType();
        if (! $propertyType instanceof ReflectionNamedType || $propertyType->getName() !== $serviceClass) {
            return "property {$entry['property']} is not $serviceClass";
        }
        if (! class_exists($serviceClass) || ! method_exists($serviceClass, $entry['sdkMethod'])) {
            return 'method is missing';
        }

        $method = new ReflectionMethod($serviceClass, $entry['sdkMethod']);
        $expectedParams = count($entry['pathParams'])
            + ($entry['hasBody'] ? 1 : 0)
            + ($entry['hasQuery'] || $entry['paginated'] ? 1 : 0);
        if (! $method->isPublic() || $method->getNumberOfParameters() !== $expectedParams) {
            return "expected a public method with $expectedParams parameter(s), got {$method->getNumberOfParameters()}";
        }

        $expectedReturn = in_array($entry['responseModel'], ['string', 'void'], true)
            ? $entry['responseModel']
            : 'Asaas\\Laravel\\Models\\' . $entry['responseModel'];
        $returnType = $method->getReturnType();
        $actualReturn = $returnType instanceof ReflectionNamedType ? $returnType->getName() : (string) $returnType;

        return $actualReturn === $expectedReturn ? null : "expected return type $expectedReturn, got $actualReturn";
    }
}
