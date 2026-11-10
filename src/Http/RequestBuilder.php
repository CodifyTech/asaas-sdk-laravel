<?php

namespace Asaas\Laravel\Http;

use Asaas\Laravel\Config\ApiKeyAuthConfig;

final class RequestBuilder
{
    private string $method = 'GET';
    private string $path = '';
    /** @var array<string, string> */
    private array $pathParams = [];
    /** @var array<string, string|int|float|bool|null> */
    private array $queryParams = [];
    /** @var array<string, string> */
    private array $headers = [];
    private mixed $body = null;
    private bool $multipart = false;

    public function __construct(
        string $path,
        string $method = 'GET',
    ) {
        $this->path = ltrim($path, '/');
        $this->method = strtoupper($method);
    }

    public function setPathParameter(string $key, string|int $value): self
    {
        $this->pathParams[$key] = rawurlencode((string) $value);
        return $this;
    }

    /** @param string|int|float|bool|null $value */
    public function setQueryParameter(string $key, $value): self
    {
        if ($value !== null) {
            $this->queryParams[$key] = $value;
        }
        return $this;
    }

    /** @param array<string, string|int|float|bool|null> $params */
    public function setQueryParameters(array $params): self
    {
        foreach ($params as $key => $value) {
            $this->setQueryParameter((string) $key, $value);
        }
        return $this;
    }

    /** @param string|int|float|bool|null $value */
    public function setOptionalQueryParameter(string $key, $value): self
    {
        return $this->setQueryParameter($key, $value);
    }

    public function setHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function setApiKeyAuth(ApiKeyAuthConfig $config): self
    {
        if ($config->apiKey !== '') {
            $this->headers[$config->apiKeyHeader] = $config->apiKey;
        }
        return $this;
    }

    public function setJsonContent(mixed $content): self
    {
        $this->body = $content;
        return $this;
    }

    /** @param array<string, mixed> $fields */
    public function setMultipartContent(array $fields): self
    {
        $this->body = $fields;
        $this->multipart = true;
        return $this;
    }

    public function buildUrl(): string
    {
        $path = $this->path;
        foreach ($this->pathParams as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }
        if ($this->queryParams !== []) {
            $query = array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v, $this->queryParams);
            $path .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $path;
    }

    /** @return array{method: string, path: string, headers: array, body: mixed, multipart: bool} */
    public function build(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->buildUrl(),
            'headers' => $this->headers,
            'body' => $this->body,
            'multipart' => $this->multipart,
        ];
    }
}
