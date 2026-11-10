<?php

declare(strict_types=1);

namespace Asaas\Laravel;

enum Environment: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://api.asaas.com',
            self::Sandbox => 'https://api-sandbox.asaas.com',
        };
    }

    /**
     * Rejects unknown values instead of guessing: a typo in ASAAS_ENV must not silently point a
     * production key at the sandbox, or the other way around.
     *
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $value): self
    {
        return match (strtolower(trim($value))) {
            'production', 'prod' => self::Production,
            'sandbox' => self::Sandbox,
            default => throw new \InvalidArgumentException(
                "Invalid Asaas environment \"{$value}\". Use \"production\" or \"sandbox\"."
            ),
        };
    }
}
