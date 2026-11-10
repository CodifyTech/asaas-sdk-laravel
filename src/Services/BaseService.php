<?php

namespace Asaas\Laravel\Services;

use Asaas\Laravel\Config\AsaasSdkConfig;
use Asaas\Laravel\Exceptions\ApiError;
use Asaas\Laravel\Exceptions\ErrorResponseDtoException;
use Asaas\Laravel\Http\AsaasHttpClient;
use Asaas\Laravel\Http\ModelConverter;
use Asaas\Laravel\Http\RequestBuilder;
use Illuminate\Http\Client\Response;

abstract class BaseService
{
    /** @var array<int, array{exception: class-string}> */
    protected array $errorMappings = [
        400 => ['exception' => ErrorResponseDtoException::class],
    ];

    public function __construct(
        protected AsaasHttpClient $httpClient,
        protected AsaasSdkConfig $config,
    ) {
    }

    protected function addErrorMapping(int $status, string $exceptionClass = ErrorResponseDtoException::class): void
    {
        $this->errorMappings[$status] = ['exception' => $exceptionClass];
    }

    /**
     * @param array{method: string, path: string, headers: array, body: mixed, multipart?: bool} $request
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

        $status = $response->status();
        $mapping = $this->errorMappings[$status] ?? null;

        if ($mapping !== null && $mapping['exception'] === ErrorResponseDtoException::class) {
            throw ErrorResponseDtoException::fromResponse($response);
        }

        throw ApiError::fromResponse($response);
    }

    /**
     * @param array{method: string, path: string, headers: array, body: mixed, multipart?: bool} $request
     * @param class-string $dtoClass
     * @return object
     */
    protected function executeAndMap(array $request, string $dtoClass): object
    {
        $response = $this->execute($request);
        return ModelConverter::toObject($response, $dtoClass);
    }

    protected function buildRequest(RequestBuilder $builder): array
    {
        return $builder->build();
    }
}
