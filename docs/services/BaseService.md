# BaseService

## Propósito

Classe base abstrata dos serviços do SDK. Centraliza a execução de requisições HTTP contra a API Asaas, o mapeamento de respostas para objetos tipados e o tratamento de erros por status HTTP. Todos os serviços de domínio (CustomerService, PaymentService, etc.) estendem BaseService e utilizam seus métodos protegidos para enviar requisições e converter respostas. Usa `AsaasHttpClient` e `RequestBuilder`; retornos são mapeados para Models via `ModelConverter`.

---

## Endpoint base

- **N/A** — BaseService não possui endpoint próprio; cada serviço filho define seu path (ex.: `v3/customers`, `v3/payments`).

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\BaseService`

### Contrato base (métodos protegidos)

| Método | Descrição |
|--------|-----------|
| `execute(array $request): Response` | Envia a requisição via `AsaasHttpClient::send()`. Se a resposta não for bem-sucedida, aplica o mapeamento de erros (`errorMappings`) ou lança `ApiError`. Retorna `Illuminate\Http\Client\Response` em caso de sucesso. |
| `executeAndMap(array $request, string $dtoClass): object` | Chama `execute($request)` e converte o corpo da resposta para uma instância da classe `$dtoClass` (Model) via `ModelConverter::toObject()`. Usado por todos os métodos dos serviços que retornam um objeto tipado (ex.: `Customer`, `CustomerList`). |
| `buildRequest(RequestBuilder $builder): array` | Obtém o array de requisição (method, path, headers, body) a partir do `RequestBuilder`. Os serviços montam o builder e passam para `buildRequest` antes de chamar `execute` ou `executeAndMap`. |
| `addErrorMapping(int $status, string $exceptionClass = ErrorResponseDtoException::class): void` | Registra para um status HTTP (ex.: 400) qual exceção lançar. Quando a API retorna esse status, o SDK deserializa o corpo para o modelo de erro e lança a exceção configurada (ex.: `ErrorResponseDtoException`). |

### Mapeamento de erros

- **Propriedade:** `protected array $errorMappings` — mapeia status code → `['exception' => class-string]`.
- Se a resposta não for `successful()` e existir mapeamento para o status, é lançada a exceção configurada (com `ErrorResponseDtoException::fromResponse($response)` quando a classe for `ErrorResponseDtoException`).
- Caso não haja mapeamento, é lançado `ApiError::fromResponse($response)`.

### Exemplo (uso indireto via serviço)

```php
// BaseService não é instanciado diretamente; os serviços herdam e usam execute/executeAndMap.
$customerService = asaas()->customer;
$list = $customerService->list(['limit' => 10]); // internamente: buildRequest + executeAndMap(.., CustomerList::class)
```


---

## Referência API Asaas

- Documentação geral da API: [https://docs.asaas.com](https://docs.asaas.com). 
- BaseService é interno aos SDKs; os endpoints reais são os dos recursos (ex.: clientes, cobranças) documentados por serviço.
