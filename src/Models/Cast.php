<?php

declare(strict_types=1);

namespace Asaas\Laravel\Models;

/**
 * Tolerant conversions for API payloads. A field whose shape changes upstream (a number sent as a
 * string, an object where a scalar was expected) becomes null instead of a TypeError, and the raw
 * value stays available in the model's $data.
 *
 * @internal
 */
final class Cast
{
    public static function string(mixed $value): ?string
    {
        return is_scalar($value) && ! is_bool($value) ? (string) $value : null;
    }

    public static function int(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && is_finite($value)) {
            return (int) $value;
        }
        if (is_string($value)) {
            $int = filter_var(trim($value), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

            return is_int($int) ? $int : null;
        }

        return null;
    }

    public static function float(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $float = filter_var(trim($value), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);

            return is_float($float) ? $float : null;
        }

        return null;
    }

    public static function bool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        return null;
    }

    /** @return array<array-key, mixed> */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
