<?php

namespace Asaas\Laravel\Config;

use Illuminate\Contracts\Support\Arrayable;

final class RetryConfig implements Arrayable
{
    public function __construct(
        public readonly int $maxRetries = 1,
        public readonly int $initialDelay = 150,
        public readonly int $maxDelay = 1000,
        public readonly float $backoffFactor = 2.0,
        public readonly int $jitter = 150,
        /** @var int[] */
        public readonly array $statusCodesToRetry = [408, 429, 500, 502, 503, 504],
        /** @var string[] */
        public readonly array $methodsToRetry = ['GET', 'PUT', 'DELETE'],
    ) {
    }

    public static function fromArray(array $config): self
    {
        return new self(
            maxRetries: $config['max_retries'] ?? 1,
            initialDelay: $config['initial_delay'] ?? 150,
            maxDelay: $config['max_delay'] ?? 1000,
            backoffFactor: (float) ($config['backoff_factor'] ?? 2),
            jitter: $config['jitter'] ?? 150,
            statusCodesToRetry: $config['status_codes'] ?? [408, 429, 500, 502, 503, 504],
            methodsToRetry: $config['methods'] ?? ['GET', 'PUT', 'DELETE'],
        );
    }

    public function toArray(): array
    {
        return [
            'max_retries' => $this->maxRetries,
            'initial_delay' => $this->initialDelay,
            'max_delay' => $this->maxDelay,
            'backoff_factor' => $this->backoffFactor,
            'jitter' => $this->jitter,
            'status_codes' => $this->statusCodesToRetry,
            'methods' => $this->methodsToRetry,
        ];
    }
}
