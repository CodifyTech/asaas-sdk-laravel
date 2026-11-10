<?php

declare(strict_types=1);

namespace Asaas\Laravel\Http;

use Asaas\Laravel\Config\AsaasSdkConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final readonly class AsaasHttpClient
{
    private const array METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private AsaasSdkConfig $config,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @param  mixed  $body  JSON string/array, or the multipart field map when $multipart is true
     *
     * @throws \InvalidArgumentException on an unsupported method or multipart payload
     */
    public function send(string $method, string $path, array $headers = [], mixed $body = null, bool $multipart = false): Response
    {
        $method = strtoupper($method);
        if (! in_array($method, self::METHODS, true)) {
            throw new \InvalidArgumentException("Unsupported HTTP method \"{$method}\".");
        }
        if ($multipart && $method !== 'POST') {
            throw new \InvalidArgumentException('Multipart requests are only supported with POST.');
        }

        $request = $this->pendingRequest($method, $this->headers($headers));

        if ($multipart) {
            return $request->asMultipart()->post($path, $this->buildMultipartParts(is_array($body) ? $body : []));
        }

        return match ($method) {
            'GET' => $request->get($path),
            'DELETE' => $request->delete($path),
            'POST' => $request->withBody($this->encodeJson($body), 'application/json')->post($path),
            'PUT' => $request->withBody($this->encodeJson($body), 'application/json')->put($path),
            'PATCH' => $request->withBody($this->encodeJson($body), 'application/json')->patch($path),
        };
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function headers(array $headers): array
    {
        // The SDK owns authentication and identification: callers cannot override them per request.
        $normalized = array_change_key_case($headers);
        $auth = $this->config->getApiKeyAuthConfig();
        unset($headers[$auth->apiKeyHeader], $headers['User-Agent'], $headers['user-agent']);

        if ($auth->apiKey !== '') {
            $headers[$auth->apiKeyHeader] = $auth->apiKey;
        }
        $headers['User-Agent'] = $this->config->userAgent;
        if (! array_key_exists('accept', $normalized)) {
            $headers['Accept'] = 'application/json';
        }

        return $headers;
    }

    /** @param  array<string, string>  $headers */
    private function pendingRequest(string $method, array $headers): PendingRequest
    {
        $retry = $this->config->retryConfig;
        $timeout = $this->config->timeout / 1000;

        $request = Http::baseUrl(rtrim($this->config->getBaseUrl(), '/').'/')
            ->withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout(min($timeout, 10))
            ->withOptions(['allow_redirects' => false]);

        if ($retry->maxRetries === 0 || ! $retry->shouldRetryMethod($method)) {
            return $request;
        }

        return $request->retry(
            $retry->attempts(),
            static fn (int $attempt, ?\Throwable $e = null): int => $retry->delayFor($attempt, self::retryAfterMs($e)),
            static function (\Throwable $e) use ($retry): bool {
                if ($e instanceof RequestException) {
                    return $retry->shouldRetryStatus($e->response->status());
                }

                // Connection failures and timeouts: the request may not have reached the API.
                return true;
            },
            throw: false,
        );
    }

    /** Reads a numeric Retry-After header (seconds) from a failed response. */
    private static function retryAfterMs(?\Throwable $e): ?int
    {
        if (! $e instanceof RequestException) {
            return null;
        }
        $header = trim($e->response->header('Retry-After'));

        return ctype_digit($header) ? (int) $header * 1000 : null;
    }

    private function encodeJson(mixed $body): string
    {
        if (is_string($body)) {
            return $body;
        }

        return $body === null || $body === [] ? '{}' : json_encode($body, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $fields
     * @return list<array{name: string, contents: string, filename?: string}>
     */
    private function buildMultipartParts(array $fields): array
    {
        $parts = [];
        foreach ($fields as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item !== null) {
                    $parts[] = $this->buildPart((string) $name, $item);
                }
            }
        }

        return $parts;
    }

    /** @return array{name: string, contents: string, filename?: string} */
    private function buildPart(string $name, mixed $value): array
    {
        // Whole file in memory: Asaas upload limits are small; stream if that changes.
        if ($value instanceof \SplFileInfo) {
            $contents = $value->isFile() && $value->isReadable() ? file_get_contents($value->getPathname()) : false;
            if ($contents === false) {
                throw new \InvalidArgumentException("Multipart field '{$name}' points to an unreadable file.");
            }
            $filename = method_exists($value, 'getClientOriginalName') ? (string) $value->getClientOriginalName() : $value->getFilename();

            return ['name' => $name, 'contents' => $contents, 'filename' => self::safeFilename($filename, $name)];
        }
        if (is_resource($value)) {
            $contents = stream_get_contents($value);
            if ($contents === false) {
                throw new \InvalidArgumentException("Multipart field '{$name}' has an unreadable stream.");
            }
            $uri = (string) (stream_get_meta_data($value)['uri'] ?? '');

            return ['name' => $name, 'contents' => $contents, 'filename' => self::safeFilename($uri, $name)];
        }
        if (is_bool($value)) {
            return ['name' => $name, 'contents' => $value ? 'true' : 'false'];
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return ['name' => $name, 'contents' => (string) $value];
        }
        throw new \InvalidArgumentException("Multipart field '{$name}' has an unsupported value type.");
    }

    /** Strips directories and header-breaking characters from a client-supplied filename. */
    private static function safeFilename(string $filename, string $fallback): string
    {
        $base = basename(str_replace('\\', '/', $filename));
        $clean = (string) preg_replace('/[\x00-\x1F\x7F"]/', '', $base);

        return $clean === '' || $clean === '.' || $clean === '..' ? $fallback : $clean;
    }
}
