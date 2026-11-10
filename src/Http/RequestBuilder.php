<?php

declare(strict_types=1);

namespace Asaas\Laravel\Http;

use Asaas\Laravel\Config\ApiKeyAuthConfig;

final class RequestBuilder
{
    private readonly string $method;

    private readonly string $path;

    /** @var array<string, string> */
    private array $pathParams = [];

    /** @var array<string, string|int|float|bool> */
    private array $queryParams = [];

    /** @var array<string, string> */
    private array $headers = [];

    private mixed $body = null;

    private bool $multipart = false;

    public function __construct(string $path, string $method = 'GET')
    {
        $this->path = ltrim($path, '/');
        $this->method = strtoupper($method);
    }

    /**
     * Encodes the value as a single path segment. Empty, "." and ".." are rejected: once resolved
     * by the HTTP client they would point the request at a different endpoint (path traversal).
     *
     * @throws \InvalidArgumentException
     */
    public function setPathParameter(string $key, string|int $value): self
    {
        $value = (string) $value;
        if (trim($value) === '' || $value === '.' || $value === '..') {
            throw new \InvalidArgumentException("The path parameter \"{$key}\" must be a non-empty identifier.");
        }
        $this->pathParams[$key] = rawurlencode($value);

        return $this;
    }

    public function setQueryParameter(string $key, string|int|float|bool|null $value): self
    {
        if ($value !== null) {
            $this->queryParams[$key] = $value;
        }

        return $this;
    }

    /** @param  array<array-key, mixed>  $params */
    public function setQueryParameters(array $params): self
    {
        foreach ($params as $key => $value) {
            if (is_array($value) || is_object($value) || is_resource($value)) {
                throw new \InvalidArgumentException("The query parameter \"{$key}\" must be a scalar value.");
            }
            $this->setQueryParameter((string) $key, $value);
        }

        return $this;
    }

    public function setOptionalQueryParameter(string $key, string|int|float|bool|null $value): self
    {
        return $this->setQueryParameter($key, $value);
    }

    public function setHeader(string $key, string $value): self
    {
        if (preg_match('/[\r\n\x00]/', $key.$value) === 1) {
            throw new \InvalidArgumentException("The header \"{$key}\" contains a line break.");
        }
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

    /** @param  array<string, mixed>  $fields */
    public function setMultipartContent(array $fields): self
    {
        $this->body = $fields;
        $this->multipart = true;

        return $this;
    }

    public function buildUrl(): string
    {
        $replacements = [];
        foreach ($this->pathParams as $key => $value) {
            $replacements['{'.$key.'}'] = $value;
        }
        // strtr replaces in one pass, so a value can never inject another placeholder.
        $path = strtr($this->path, $replacements);

        if (preg_match('/\{[^}]+}/', $path, $missing) === 1) {
            throw new \LogicException("Missing path parameter {$missing[0]} for {$this->path}.");
        }

        if ($this->queryParams !== []) {
            $query = array_map(static fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v, $this->queryParams);
            $path .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $path;
    }

    /** @return array{method: string, path: string, headers: array<string, string>, body: mixed, multipart: bool} */
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
