<?php

declare(strict_types=1);

namespace Asaas\Laravel\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class ApiError extends RuntimeException
{
    /** Keeps HTML error pages and large payloads out of exception messages and logs. */
    protected const int MAX_MESSAGE_LENGTH = 500;

    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?Response $response = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function fromResponse(Response $response): self
    {
        return new self(static::messageFrom($response), $response->status(), $response);
    }

    /** First Asaas error description, or a truncated body when the payload is not the documented shape. */
    protected static function messageFrom(Response $response): string
    {
        $body = $response->json();
        $description = is_array($body) ? ($body['errors'][0]['description'] ?? null) : null;
        if (is_string($description) && $description !== '') {
            return $description;
        }

        $raw = trim($response->body());
        if ($raw === '') {
            return "Asaas API request failed with HTTP {$response->status()}.";
        }

        return mb_strlen($raw) > static::MAX_MESSAGE_LENGTH
            ? mb_substr($raw, 0, static::MAX_MESSAGE_LENGTH).'…'
            : $raw;
    }
}
