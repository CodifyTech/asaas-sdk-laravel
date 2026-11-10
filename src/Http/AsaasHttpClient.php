<?php

namespace Asaas\Laravel\Http;

use Asaas\Laravel\Config\AsaasSdkConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class AsaasHttpClient
{
    public function __construct(
        private readonly AsaasSdkConfig $config,
    ) {
    }

    /**
     * @param array<string, string> $headers
     * @param mixed $body JSON string/array, or the multipart field map when $multipart is true
     */
    public function send(string $method, string $path, array $headers = [], mixed $body = null, bool $multipart = false): Response
    {
        $auth = $this->config->getApiKeyAuthConfig();
        if ($auth->apiKey !== '') {
            $headers[$auth->apiKeyHeader] = $auth->apiKey;
        }
        $headers['User-Agent'] = $this->config->userAgent;
        if (! array_key_exists('accept', array_change_key_case($headers))) {
            $headers['Accept'] = 'application/json';
        }

        $baseUrl = rtrim($this->config->getBaseUrl(), '/') . '/';
        $request = Http::baseUrl($baseUrl)
            ->withHeaders($headers)
            ->timeout($this->config->timeout / 1000);

        $retry = $this->config->retryConfig;
        $request = $request->retry(
            $retry->maxRetries,
            $retry->initialDelay,
            function (\Exception $e, PendingRequest $req) use ($retry, $method): bool {
                if (! in_array(strtoupper($method), $retry->methodsToRetry, true)) {
                    return false;
                }
                if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
                    return in_array($e->response->status(), $retry->statusCodesToRetry, true);
                }
                return true;
            },
            throw: false
        );

        $method = strtoupper($method);
        if ($multipart) {
            if ($method !== 'POST') {
                throw new \InvalidArgumentException('Multipart requests are only supported with POST.');
            }
            return $request->asMultipart()->post($path, $this->buildMultipartParts(is_array($body) ? $body : []));
        }

        return match ($method) {
            'GET' => $request->get($path),
            'POST' => $request->withBody($this->encodeJson($body), 'application/json')->post($path),
            'PUT' => $request->withBody($this->encodeJson($body), 'application/json')->put($path),
            'PATCH' => $request->withBody($this->encodeJson($body), 'application/json')->patch($path),
            'DELETE' => $request->delete($path),
            default => $request->send($method, $path, array_filter(['body' => $body])),
        };
    }

    private function encodeJson(mixed $body): string
    {
        if (is_string($body)) {
            return $body;
        }
        return $body === null || $body === [] ? '{}' : json_encode($body, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $fields
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
        // shortcut: whole file in memory; Asaas upload limits are small, stream if that changes.
        if ($value instanceof \SplFileInfo) {
            $contents = $value->isFile() ? file_get_contents($value->getPathname()) : false;
            if ($contents === false) {
                throw new \InvalidArgumentException("Multipart field '{$name}' points to an unreadable file.");
            }
            $filename = method_exists($value, 'getClientOriginalName') ? $value->getClientOriginalName() : $value->getFilename();
            return ['name' => $name, 'contents' => $contents, 'filename' => $filename];
        }
        if (is_resource($value)) {
            $contents = stream_get_contents($value);
            if ($contents === false) {
                throw new \InvalidArgumentException("Multipart field '{$name}' has an unreadable stream.");
            }
            $uri = (string) (stream_get_meta_data($value)['uri'] ?? '');
            return ['name' => $name, 'contents' => $contents, 'filename' => basename($uri) ?: $name];
        }
        if (is_bool($value)) {
            return ['name' => $name, 'contents' => $value ? 'true' : 'false'];
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return ['name' => $name, 'contents' => (string) $value];
        }
        throw new \InvalidArgumentException("Multipart field '{$name}' has an unsupported value type.");
    }
}
