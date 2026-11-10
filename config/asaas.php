<?php

declare(strict_types=1);

return [
    // Never commit the key: read it from the environment (or a secrets manager).
    'api_key' => env('ASAAS_API_KEY', ''),

    // production | sandbox. Any other value throws InvalidArgumentException.
    'environment' => env('ASAAS_ENV', 'sandbox'),

    // Optional override. Must be https:// (http:// only for localhost mock servers).
    'base_url' => env('ASAAS_BASE_URL'),

    // Milliseconds.
    'timeout' => (int) env('ASAAS_TIMEOUT', 10000),

    // null uses the SDK default (codifytech-asaas-sdk-laravel/<version>).
    'user_agent' => env('ASAAS_USER_AGENT'),

    'retry' => [
        // Retries after the first attempt (0 to 10). 0 disables retry.
        'max_retries' => (int) env('ASAAS_RETRY_MAX', 1),
        // Exponential backoff: min(initial_delay × backoff_factor^(n-1), max_delay) + random(0, jitter), in ms.
        'initial_delay' => (int) env('ASAAS_RETRY_INITIAL_DELAY', 150),
        'max_delay' => (int) env('ASAAS_RETRY_MAX_DELAY', 1000),
        'backoff_factor' => (float) env('ASAAS_RETRY_BACKOFF_FACTOR', 2),
        'jitter' => (int) env('ASAAS_RETRY_JITTER', 150),
        'status_codes' => [408, 429, 500, 502, 503, 504],
        // POST is not retried: the Asaas API has no idempotency key, so a retry could duplicate a charge.
        'methods' => ['GET', 'PUT', 'DELETE'],
    ],
];
