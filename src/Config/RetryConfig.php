<?php

declare(strict_types=1);

namespace Asaas\Laravel\Config;

use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, mixed> */
final readonly class RetryConfig implements Arrayable
{
    /** Upper bound that keeps a misconfiguration from hammering the API. */
    public const int MAX_RETRIES_LIMIT = 10;

    /** @var list<int> */
    public array $statusCodesToRetry;

    /** @var list<string> */
    public array $methodsToRetry;

    /**
     * @param  int  $maxRetries  retries after the first attempt (0 disables retry)
     * @param  int  $initialDelay  milliseconds before the first retry
     * @param  int  $maxDelay  cap, in milliseconds, for the computed delay (jitter excluded)
     * @param  int  $jitter  random milliseconds (0 to $jitter) added to each delay
     * @param  array<int|string>  $statusCodesToRetry
     * @param  array<string>  $methodsToRetry
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public int $maxRetries = 1,
        public int $initialDelay = 150,
        public int $maxDelay = 1000,
        public float $backoffFactor = 2.0,
        public int $jitter = 150,
        array $statusCodesToRetry = [408, 429, 500, 502, 503, 504],
        array $methodsToRetry = ['GET', 'PUT', 'DELETE'],
    ) {
        if ($maxRetries < 0 || $maxRetries > self::MAX_RETRIES_LIMIT) {
            throw new \InvalidArgumentException('retry.max_retries must be between 0 and '.self::MAX_RETRIES_LIMIT.'.');
        }
        if ($initialDelay < 0 || $maxDelay < 0 || $jitter < 0) {
            throw new \InvalidArgumentException('retry delays and jitter cannot be negative.');
        }
        if ($backoffFactor < 1.0) {
            throw new \InvalidArgumentException('retry.backoff_factor must be at least 1.');
        }

        $this->statusCodesToRetry = array_values(array_map(intval(...), $statusCodesToRetry));
        $this->methodsToRetry = array_values(array_map(
            static fn (string $method): string => strtoupper(trim($method)),
            $methodsToRetry,
        ));
    }

    /** @param  array<string, mixed>  $config */
    public static function fromArray(array $config): self
    {
        return new self(
            maxRetries: (int) ($config['max_retries'] ?? 1),
            initialDelay: (int) ($config['initial_delay'] ?? 150),
            maxDelay: (int) ($config['max_delay'] ?? 1000),
            backoffFactor: (float) ($config['backoff_factor'] ?? 2),
            jitter: (int) ($config['jitter'] ?? 150),
            statusCodesToRetry: (array) ($config['status_codes'] ?? [408, 429, 500, 502, 503, 504]),
            methodsToRetry: (array) ($config['methods'] ?? ['GET', 'PUT', 'DELETE']),
        );
    }

    /** Total attempts, as Laravel's PendingRequest::retry() counts them. */
    public function attempts(): int
    {
        return $this->maxRetries + 1;
    }

    public function shouldRetryMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->methodsToRetry, true);
    }

    public function shouldRetryStatus(int $status): bool
    {
        return in_array($status, $this->statusCodesToRetry, true);
    }

    /**
     * Exponential backoff: min(initial × factor^(attempt-1), max) + random jitter.
     *
     * @param  int  $attempt  1 for the first retry
     * @param  int|null  $retryAfterMs  server hint (Retry-After), honoured up to $maxDelay
     */
    public function delayFor(int $attempt, ?int $retryAfterMs = null): int
    {
        $delay = $this->initialDelay * ($this->backoffFactor ** max(0, $attempt - 1));
        if ($retryAfterMs !== null) {
            $delay = max($delay, $retryAfterMs);
        }
        $delay = (int) min($delay, $this->maxDelay);

        return $this->jitter > 0 ? $delay + random_int(0, $this->jitter) : $delay;
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
