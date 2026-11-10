<?php

return [
    'api_key' => env('ASAAS_API_KEY', ''),
    'environment' => env('ASAAS_ENV', 'sandbox'), // production | sandbox
    'base_url' => env('ASAAS_BASE_URL'), // optional override
    'timeout' => (int) env('ASAAS_TIMEOUT', 10000),
    'user_agent' => env('ASAAS_USER_AGENT', 'asaas-api-sdk-laravel/1.0'),
    'retry' => [
        'max_retries' => (int) env('ASAAS_RETRY_MAX', 1),
        'initial_delay' => (int) env('ASAAS_RETRY_INITIAL_DELAY', 150),
        'max_delay' => (int) env('ASAAS_RETRY_MAX_DELAY', 1000),
        'backoff_factor' => (float) env('ASAAS_RETRY_BACKOFF_FACTOR', 2),
        'jitter' => (int) env('ASAAS_RETRY_JITTER', 150),
        'status_codes' => [408, 429, 500, 502, 503, 504],
        'methods' => ['GET', 'PUT', 'DELETE'],
    ],
];
