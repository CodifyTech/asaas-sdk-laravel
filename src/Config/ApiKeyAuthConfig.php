<?php

declare(strict_types=1);

namespace Asaas\Laravel\Config;

final readonly class ApiKeyAuthConfig
{
    public function __construct(
        #[\SensitiveParameter]
        public string $apiKey,
        public string $apiKeyHeader = 'access_token',
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(#[\SensitiveParameter] array $config): self
    {
        return new self(
            apiKey: (string) ($config['api_key'] ?? ''),
            apiKeyHeader: (string) ($config['api_key_header'] ?? 'access_token'),
        );
    }

    /** Keeps the key out of dd(), var_dump() and print_r() output. */
    public function __debugInfo(): array
    {
        return [
            'apiKey' => $this->apiKey === '' ? '' : '***',
            'apiKeyHeader' => $this->apiKeyHeader,
        ];
    }
}
