<?php

declare(strict_types=1);

namespace Asaas\Laravel\Config;

use Asaas\Laravel\Environment;

final readonly class AsaasSdkConfig
{
    public const string DEFAULT_USER_AGENT = 'codifytech-asaas-sdk-laravel/1.0.3';

    /** Hosts allowed over plain HTTP, for local mock servers only. */
    private const array LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]', '::1'];

    /**
     * @param  int  $timeout  request timeout in milliseconds
     *
     * @throws \InvalidArgumentException on an unsafe base URL or a non-positive timeout
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $apiKey,
        public Environment $environment,
        public ?string $baseUrl = null,
        public int $timeout = 10000,
        public string $userAgent = self::DEFAULT_USER_AGENT,
        public RetryConfig $retryConfig = new RetryConfig,
        public string $apiKeyHeader = 'access_token',
    ) {
        if ($baseUrl !== null) {
            self::assertSafeBaseUrl($baseUrl);
        }
        if ($timeout < 1) {
            throw new \InvalidArgumentException('The Asaas timeout must be at least 1 millisecond.');
        }
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl ?? $this->environment->baseUrl();
    }

    public function getApiKeyAuthConfig(): ApiKeyAuthConfig
    {
        return new ApiKeyAuthConfig($this->apiKey, $this->apiKeyHeader);
    }

    /**
     * @param  array<string, mixed>  $config  the `asaas` config array
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(#[\SensitiveParameter] array $config): self
    {
        $environment = isset($config['environment']) && $config['environment'] !== ''
            ? Environment::fromString((string) $config['environment'])
            : Environment::Sandbox;

        $retry = isset($config['retry']) && is_array($config['retry'])
            ? RetryConfig::fromArray($config['retry'])
            : new RetryConfig;

        $baseUrl = $config['base_url'] ?? null;
        $userAgent = $config['user_agent'] ?? null;

        return new self(
            apiKey: (string) ($config['api_key'] ?? ''),
            environment: $environment,
            baseUrl: $baseUrl === null || $baseUrl === '' ? null : (string) $baseUrl,
            timeout: (int) ($config['timeout'] ?? 10000),
            userAgent: $userAgent === null || $userAgent === '' ? self::DEFAULT_USER_AGENT : (string) $userAgent,
            retryConfig: $retry,
            apiKeyHeader: (string) ($config['api_key_header'] ?? 'access_token'),
        );
    }

    /** Keeps the key out of dd(), var_dump() and print_r() output. */
    public function __debugInfo(): array
    {
        return [
            'apiKey' => $this->apiKey === '' ? '' : '***',
            'environment' => $this->environment,
            'baseUrl' => $this->baseUrl,
            'timeout' => $this->timeout,
            'userAgent' => $this->userAgent,
            'retryConfig' => $this->retryConfig,
            'apiKeyHeader' => $this->apiKeyHeader,
        ];
    }

    /**
     * The API key travels in a header, so it must never go over plain HTTP to a remote host, nor to a
     * URL carrying credentials, a query or a fragment that would mangle every request path.
     */
    private static function assertSafeBaseUrl(string $baseUrl): void
    {
        $parts = parse_url($baseUrl);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        $valid = is_array($parts)
            && $host !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment'])
            && ($scheme === 'https' || ($scheme === 'http' && in_array($host, self::LOOPBACK_HOSTS, true)));

        if (! $valid) {
            throw new \InvalidArgumentException(
                'The Asaas base URL must be an https:// URL without credentials, query or fragment '
                .'(plain http:// is accepted only for localhost).'
            );
        }
    }
}
