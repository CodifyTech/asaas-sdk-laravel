<?php

declare(strict_types=1);

namespace Asaas\Laravel\Tests\Http;

use Asaas\Laravel\AsaasServiceProvider;
use Asaas\Laravel\Config\AsaasSdkConfig;
use Asaas\Laravel\Exceptions\ErrorResponseDtoException;
use Asaas\Laravel\Http\AsaasHttpClient;
use Asaas\Laravel\Http\RequestBuilder;
use Asaas\Laravel\Services\BaseService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class AsaasHttpClientTest extends TestCase
{
    // Untyped $app on purpose: Testbench 9 through 11 declare these hooks without a parameter type.
    protected function getPackageProviders($app)
    {
        return [AsaasServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Only api_key is set: the rest must come from mergeConfigFrom (no vendor:publish).
        $app['config']->set('asaas.api_key', 'key_test');
    }

    public function test_config_defaults_are_merged_without_publishing(): void
    {
        $this->assertSame('key_test', config('asaas.api_key'));
        $this->assertSame('sandbox', config('asaas.environment'));
        $this->assertSame(['GET', 'PUT', 'DELETE'], config('asaas.retry.methods'));
    }

    public function test_json_body_has_json_content_type_and_default_accept(): void
    {
        Http::fake(['*' => Http::response(['id' => 'x'])]);

        $this->client()->send('POST', 'v3/things', [], ['a' => 1]);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Accept', 'application/json')
            && $r->hasHeader('Content-Type', 'application/json')
            && $r->hasHeader('access_token', 'key_test')
            && $r->body() === '{"a":1}');
    }

    public function test_null_and_empty_array_bodies_are_sent_as_empty_json_object(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->client()->send('POST', 'v3/a', [], null);
        $this->client()->send('PUT', 'v3/b', [], []);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'v3/a') && $r->body() === '{}');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'v3/b') && $r->body() === '{}');
    }

    public function test_get_has_no_forced_content_type(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->client()->send('GET', 'v3/things');

        Http::assertSent(fn (Request $r) => ! $r->hasHeader('Content-Type'));
    }

    public function test_caller_accept_header_overrides_default(): void
    {
        Http::fake(['*' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf'])]);

        $this->client()->send('GET', 'v3/book', ['Accept' => 'application/pdf, application/json']);

        Http::assertSent(fn (Request $r) => $r->header('Accept') === ['application/pdf, application/json']);
    }

    public function test_pdf_body_is_returned_as_raw_string_through_base_service(): void
    {
        Http::fake(['*' => Http::response('%PDF-1.4 binary', 200, ['Content-Type' => 'application/pdf'])]);

        $body = $this->service()->pdf();

        $this->assertSame('%PDF-1.4 binary', $body);
    }

    public function test_no_content_response_is_successful_and_empty(): void
    {
        Http::fake(['*' => Http::response(null, 204)]);

        $response = $this->service()->noContent();

        $this->assertSame(204, $response->status());
        $this->assertSame('', $response->body());
    }

    public function test_bad_request_maps_to_error_response_dto_exception_by_default(): void
    {
        Http::fake(['*' => Http::response(['errors' => [['code' => 'c', 'description' => 'd']]], 400)]);

        $this->expectException(ErrorResponseDtoException::class);

        $this->service()->noContent();
    }

    public function test_multipart_sends_parts_for_scalars_booleans_and_nulls(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->client()->send('POST', 'v3/up', [], ['type' => 'IDENTIFICATION', 'flag' => true, 'off' => false, 'n' => 5, 'skip' => null], true);

        Http::assertSent(function (Request $r) {
            $parts = $this->partsByName($r);

            return $r->isMultipart()
                && $parts['type'] === 'IDENTIFICATION'
                && $parts['flag'] === 'true'
                && $parts['off'] === 'false'
                && $parts['n'] === '5'
                && ! array_key_exists('skip', $parts);
        });
    }

    public function test_multipart_reads_uploaded_file_with_client_name(): void
    {
        Http::fake(['*' => Http::response([])]);
        $file = UploadedFile::fake()->create('doc.pdf', 1);

        $this->client()->send('POST', 'v3/up', [], ['documentFile' => $file], true);

        Http::assertSent(function (Request $r) use ($file) {
            $part = $r->data()[0] ?? [];

            return $r->isMultipart()
                && $part['name'] === 'documentFile'
                && $part['filename'] === 'doc.pdf'
                && $part['contents'] === file_get_contents($file->getPathname());
        });
    }

    public function test_multipart_reads_plain_spl_file_info_and_stream(): void
    {
        Http::fake(['*' => Http::response([])]);
        $path = tempnam(sys_get_temp_dir(), 'asaas');
        file_put_contents($path, 'spl-content');
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, 'stream-content');
        rewind($stream);

        $this->client()->send('POST', 'v3/up', [], ['a' => new \SplFileInfo($path), 'b' => $stream], true);
        unlink($path);

        Http::assertSent(function (Request $r) use ($path) {
            [$a, $b] = $r->data();

            return $a['contents'] === 'spl-content' && $a['filename'] === basename($path)
                && $b['contents'] === 'stream-content' && $b['filename'] !== '';
        });
    }

    public function test_multipart_array_value_becomes_repeated_parts(): void
    {
        Http::fake(['*' => Http::response([])]);
        $one = UploadedFile::fake()->create('one.pdf', 1);
        $two = UploadedFile::fake()->create('two.pdf', 1);

        $this->client()->send('POST', 'v3/up', [], ['files' => [$one, $two], 'tags' => ['x', 'y']], true);

        Http::assertSent(function (Request $r) {
            $data = $r->data();

            return count($data) === 4
                && $data[0]['name'] === 'files' && $data[0]['filename'] === 'one.pdf'
                && $data[1]['name'] === 'files' && $data[1]['filename'] === 'two.pdf'
                && $data[2] === ['name' => 'tags', 'contents' => 'x']
                && $data[3] === ['name' => 'tags', 'contents' => 'y'];
        });
    }

    public function test_multipart_plain_string_is_never_read_as_a_path(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->client()->send('POST', 'v3/up', [], ['file' => __FILE__], true);

        Http::assertSent(function (Request $r) {
            $part = $r->data()[0];

            return $part['contents'] === __FILE__ && ! array_key_exists('filename', $part);
        });
    }

    public function test_multipart_with_missing_file_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->client()->send('POST', 'v3/up', [], ['f' => new \SplFileInfo('/nonexistent/file.pdf')], true);
    }

    public function test_multipart_requires_post(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->client()->send('PUT', 'v3/up', [], ['a' => 'b'], true);
    }

    public function test_path_params_are_percent_encoded_and_query_booleans_are_words(): void
    {
        Http::fake(['*' => Http::response([])]);
        $builder = (new RequestBuilder('v3/things/{id}', 'GET'))
            ->setPathParameter('id', 'a/b c')
            ->setQueryParameters(['flag' => true, 'off' => false, 'paymentDate[ge]' => '2026-01-01', 'skip' => null]);
        $request = $builder->build();

        $this->client()->send($request['method'], $request['path']);

        Http::assertSent(fn (Request $r) => $r->url()
            === 'https://api-sandbox.asaas.com/v3/things/a%2Fb%20c?flag=true&off=false&paymentDate%5Bge%5D=2026-01-01');
    }

    private function client(): AsaasHttpClient
    {
        return new AsaasHttpClient(AsaasSdkConfig::fromArray(['api_key' => 'key_test', 'retry' => ['max_retries' => 1]]));
    }

    private function service(): object
    {
        $config = AsaasSdkConfig::fromArray(['api_key' => 'key_test']);

        return new class(new AsaasHttpClient($config), $config) extends BaseService
        {
            public function pdf(): string
            {
                return $this->execute($this->buildRequest(new RequestBuilder('v3/book')))->body();
            }

            public function noContent(): Response
            {
                return $this->execute($this->buildRequest(new RequestBuilder('v3/x', 'DELETE')));
            }
        };
    }

    /** @return array<string, string> */
    private function partsByName(Request $request): array
    {
        $out = [];
        foreach ($request->data() as $part) {
            $out[$part['name']] = $part['contents'];
        }

        return $out;
    }
}
