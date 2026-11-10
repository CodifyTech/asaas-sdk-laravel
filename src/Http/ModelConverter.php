<?php

declare(strict_types=1);

namespace Asaas\Laravel\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class ModelConverter
{
    /** @return array<array-key, mixed> */
    public static function toArray(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public static function toObject(Response $response, string $class): object
    {
        return self::fromArray(self::toArray($response), $class);
    }

    /**
     * @template T of object
     *
     * @param  array<array-key, mixed>  $data
     * @param  class-string<T>  $class
     * @return T
     */
    public static function fromArray(array $data, string $class): object
    {
        if (method_exists($class, 'fromArray')) {
            return $class::fromArray($data);
        }
        if (method_exists($class, 'from')) {
            return $class::from($data);
        }

        return new \ReflectionClass($class)->newInstanceArgs(self::mapConstructorArgs($data, $class));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  class-string  $class
     * @return list<mixed>
     */
    private static function mapConstructorArgs(array $data, string $class): array
    {
        $constructor = new \ReflectionClass($class)->getConstructor();
        if ($constructor === null) {
            return [];
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $args[] = Arr::get($data, Str::snake($name), Arr::get($data, $name));
        }

        return $args;
    }

    public static function toJson(mixed $model): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (is_object($model) && method_exists($model, 'toArray')) {
            return json_encode($model->toArray(), $flags);
        }

        return json_encode($model, $flags);
    }
}
