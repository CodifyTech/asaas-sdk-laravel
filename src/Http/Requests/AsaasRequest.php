<?php

namespace Asaas\Laravel\Http\Requests;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Base for generated request classes: each subclass only declares rules(). */
abstract class AsaasRequest
{
    /** @return array<string, mixed> */
    abstract public static function rules(): array;

    /**
     * Validates a body and returns it unchanged, so fields newer than the SDK still reach the API.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        Validator::make($data, static::rules())->validate();
        return $data;
    }

    /**
     * Validates a query and rejects keys without a rule: Asaas ignores unknown filters and
     * returns an unfiltered list, which would expose data.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public static function validateQuery(array $params): array
    {
        $allowed = array_map(fn ($key) => explode('.', (string) $key)[0], array_keys(static::rules()));
        $messages = [];
        foreach (array_keys($params) as $key) {
            if (! in_array((string) $key, $allowed, true)) {
                $messages[(string) $key] = ['O parâmetro de consulta "' . $key . '" não é suportado.'];
            }
        }
        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        Validator::make($params, static::rules())->validate();
        return $params;
    }

    /** Rule accepting an \SplFileInfo or a stream resource, or a list of them. */
    protected static function file(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            $isFile = static fn ($v): bool => $v instanceof \SplFileInfo || is_resource($v);
            $items = is_array($value) && array_is_list($value) ? $value : [$value];
            if ($items === [] || array_filter($items, $isFile) !== $items) {
                $fail('O campo ' . $attribute . ' deve ser um arquivo.');
            }
        };
    }
}
