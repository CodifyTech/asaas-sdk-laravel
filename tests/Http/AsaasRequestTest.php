<?php

declare(strict_types=1);

namespace Asaas\Laravel\Tests\Http;

use Asaas\Laravel\Http\Requests\AsaasRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Orchestra\Testbench\TestCase;

class AsaasRequestTest extends TestCase
{
    public function test_validate_passes_unknown_body_fields_through(): void
    {
        $data = ['name' => 'x', 'newerField' => ['a' => 1]];

        $this->assertSame($data, SampleRequest::validate($data));
    }

    public function test_validate_rejects_invalid_known_field(): void
    {
        $this->expectException(ValidationException::class);

        SampleRequest::validate(['name' => ['not', 'string']]);
    }

    public function test_validate_enforces_required(): void
    {
        $this->expectException(ValidationException::class);

        SampleRequest::validate([]);
    }

    public function test_validate_query_returns_known_keys_including_bracket_filters(): void
    {
        $params = ['name' => 'x', 'paymentDate[ge]' => '2026-01-01'];

        $this->assertSame($params, SampleRequest::validateQuery($params));
    }

    public function test_validate_query_rejects_unknown_key(): void
    {
        try {
            SampleRequest::validateQuery(['name' => 'x', 'paymentDateGe' => '2026-01-01']);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('paymentDateGe', $e->errors());
        }
    }

    public function test_file_rule_accepts_files_streams_and_lists(): void
    {
        $file = UploadedFile::fake()->create('a.pdf', 1);
        $stream = fopen('php://memory', 'r');

        foreach ([$file, new \SplFileInfo(__FILE__), $stream, [$file, $stream]] as $value) {
            $this->assertSame(['name' => 'n', 'file' => $value], SampleRequest::validate(['name' => 'n', 'file' => $value]));
        }
    }

    public function test_file_rule_rejects_plain_string_and_mixed_lists(): void
    {
        foreach (['/etc/passwd', [new \SplFileInfo(__FILE__), 'str'], []] as $value) {
            try {
                SampleRequest::validate(['name' => 'n', 'file' => $value]);
                $this->fail('Expected ValidationException.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('file', $e->errors());
            }
        }
    }
}

final class SampleRequest extends AsaasRequest
{
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'paymentDate[ge]' => ['nullable', 'date_format:Y-m-d'],
            'file' => ['nullable', self::file()],
        ];
    }
}
