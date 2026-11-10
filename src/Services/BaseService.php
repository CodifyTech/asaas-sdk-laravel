<?php

declare(strict_types=1);

namespace Asaas\Laravel\Services;

use Asaas\Laravel\Config\AsaasSdkConfig;
use Asaas\Laravel\Exceptions\ApiError;
use Asaas\Laravel\Exceptions\ErrorResponseDtoException;
use Asaas\Laravel\Http\AsaasHttpClient;
use Asaas\Laravel\Http\ModelConverter;
use Asaas\Laravel\Http\RequestBuilder;
use Illuminate\Http\Client\Response;

/**
 * @phpstan-type AsaasRequestShape array{method: string, path: string, headers?: array<string, string>, body?: mixed, multipart?: bool}
 */
abstract class BaseService
{
    /** @var array<int, array{exception: class-string<ApiError>}> */
    protected array $errorMappings = [
        400 => ['exception' => ErrorResponseDtoException::class],
    ];

    public function __construct(
        protected AsaasHttpClient $httpClient,
        protected AsaasSdkConfig $config,
    ) {}

    /** @param class-string<ApiError> $exceptionClass */
    protected function addErrorMapping(int $status, string $exceptionClass = ErrorResponseDtoException::class): void
    {
        $this->errorMappings[$status] = ['exception' => $exceptionClass];
    }

    /**
     * @param  AsaasRequestShape  $request
     *
     * @throws ApiError|ErrorResponseDtoException
     */
    protected function execute(array $request): Response
    {
        $response = $this->httpClient->send(
            $request['method'],
            $request['path'],
            $request['headers'] ?? [],
            $request['body'] ?? null,
            $request['multipart'] ?? false,
        );

        if ($response->successful()) {
            return $response;
        }

        $exception = $this->errorMappings[$response->status()]['exception'] ?? ApiError::class;

        throw $exception::fromResponse($response);
    }

    /**
     * @template T of object
     *
     * @param  AsaasRequestShape  $request
     * @param  class-string<T>  $dtoClass
     * @return T
     *
     * @throws ApiError|ErrorResponseDtoException
     */
    protected function executeAndMap(array $request, string $dtoClass): object
    {
        return ModelConverter::toObject($this->execute($request), $dtoClass);
    }

    /** @return AsaasRequestShape */
    protected function buildRequest(RequestBuilder $builder): array
    {
        return $builder->build();
    }
}
