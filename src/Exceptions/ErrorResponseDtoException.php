<?php

declare(strict_types=1);

namespace Asaas\Laravel\Exceptions;

use Illuminate\Http\Client\Response;

class ErrorResponseDtoException extends ApiError
{
    /** @param array<array-key, mixed> $errorResponse */
    public function __construct(
        public readonly array $errorResponse,
        string $message,
        int $statusCode,
        ?Response $response = null,
    ) {
        parent::__construct($message, $statusCode, $response);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();

        return new self(is_array($body) ? $body : [], static::messageFrom($response), $response->status(), $response);
    }

    /** @return list<array{code?: string, description?: string}> */
    public function getErrors(): array
    {
        $errors = $this->errorResponse['errors'] ?? [];

        return is_array($errors) ? array_values(array_filter($errors, is_array(...))) : [];
    }
}
