# Asaas API SDK Laravel

SDK Laravel para a [API Asaas v3](https://docs.asaas.com): cliente HTTP sobre o `Http` facade do Laravel, validação de entrada, um serviço por recurso e models de resposta. A API pública trabalha com arrays.

## Compatibilidade

Matriz verificada com a suíte de testes (23 testes, 42 combinações, todas passando):

| Laravel | PHP testado | PHP oficial do Laravel |
|---------|-------------|------------------------|
| 9 (>= 9.7) | 8.1 a 8.5 | 8.0 a 8.2 |
| 10 | 8.1 a 8.5 | 8.1 a 8.3 |
| 11 | 8.2 a 8.5 | 8.2 a 8.4 |
| 12 | 8.2 a 8.5 | 8.2 a 8.5 |
| 13 | 8.3 a 8.5 | 8.3 a 8.5 |

- As combinações fora da faixa oficial de PHP do Laravel passam na nossa CI, mas não são suportadas pelo próprio Laravel.
- Laravel 9, 10 e 11 não recebem mais correções de segurança do Laravel. O Composer 2.9 bloqueia essas versões por padrão; para instalar, defina `COMPOSER_NO_SECURITY_BLOCKING=1`.
- O pacote exige `guzzlehttp/guzzle` `^7.2`. O Laravel 9 e o 10 apenas sugerem o Guzzle, então ele é instalado junto com o SDK.
- O mínimo de Laravel 9.7 existe porque as versões 9.x anteriores não fazem retry de respostas com falha (veja "Retry e timeout").

## Instalação

```bash
composer require luquinhasbrito/asaas-api-sdk-laravel
```

O pacote registra `Asaas\Laravel\AsaasServiceProvider` pelo package auto-discovery do Laravel, sem registro manual. O helper global `asaas()` também é carregado automaticamente.

Publique o arquivo de configuração. Este passo é obrigatório:

```bash
php artisan vendor:publish --tag=asaas-config
```

> **Atenção:** o service provider não faz merge da configuração do pacote. Sem `config/asaas.php` na aplicação, `config('asaas')` é vazio: `ASAAS_API_KEY`, `ASAAS_ENV` e as demais variáveis são ignoradas, e o SDK passa a usar o ambiente sandbox com API key vazia (nenhum header de autenticação é enviado). Se não quiser publicar o arquivo do pacote, crie você mesmo o `config/asaas.php` com a mesma estrutura descrita abaixo.

## Configuração

Depois de publicar o arquivo, defina as variáveis no `.env`:

```env
ASAAS_API_KEY=your_api_key
ASAAS_ENV=sandbox
ASAAS_TIMEOUT=10000
ASAAS_RETRY_MAX=1
# ASAAS_BASE_URL=https://api-sandbox.asaas.com
# ASAAS_USER_AGENT=my-app/1.0
# ASAAS_RETRY_INITIAL_DELAY=150
```

Referência completa das chaves de `config/asaas.php`:

| Chave | Variável de ambiente | Padrão | Descrição |
|-------|----------------------|--------|-----------|
| `api_key` | `ASAAS_API_KEY` | `''` | Chave de API enviada no header de autenticação. Se vazia, nenhum header de autenticação é enviado. |
| `environment` | `ASAAS_ENV` | `sandbox` | Ambiente da API. Veja a seção "Ambientes" abaixo. |
| `base_url` | `ASAAS_BASE_URL` | `null` | URL base opcional. Quando definida, sobrepõe a URL do ambiente. |
| `timeout` | `ASAAS_TIMEOUT` | `10000` | Timeout da requisição em **milissegundos**, aplicado a cada tentativa. O cliente HTTP converte para segundos (`timeout / 1000`). |
| `user_agent` | `ASAAS_USER_AGENT` | `asaas-api-sdk-laravel/1.0` | Valor do header `User-Agent`. |
| `retry.max_retries` | `ASAAS_RETRY_MAX` | `1` | Número **total de tentativas**, incluindo a primeira. O padrão `1` significa nenhum retry. |
| `retry.initial_delay` | `ASAAS_RETRY_INITIAL_DELAY` | `150` | Espera fixa, em milissegundos, entre tentativas. |
| `retry.max_delay` | `ASAAS_RETRY_MAX_DELAY` | `1000` | Lido, mas atualmente ignorado pelo cliente HTTP. |
| `retry.backoff_factor` | `ASAAS_RETRY_BACKOFF_FACTOR` | `2` | Lido, mas atualmente ignorado pelo cliente HTTP. |
| `retry.jitter` | `ASAAS_RETRY_JITTER` | `150` | Lido, mas atualmente ignorado pelo cliente HTTP. |
| `retry.status_codes` | sem variável | `[408, 429, 500, 502, 503, 504]` | Status HTTP que disparam nova tentativa. |
| `retry.methods` | sem variável | `['GET', 'POST', 'PUT', 'PATCH', 'DELETE']` | Métodos HTTP elegíveis para nova tentativa (use maiúsculas). |
| `api_key_header` | sem variável | `access_token` | Opcional. Nome do header que carrega a API key. É aceito pelo SDK, mas não consta no arquivo publicado: para alterá-lo, adicione a chave ao seu `config/asaas.php`. |

### Ambientes

`environment` aceita `production` ou `prod` (sem distinção de maiúsculas e minúsculas) para produção. **Qualquer outro valor** seleciona sandbox, inclusive grafias erradas como `producao`.

| Ambiente | URL base |
|----------|----------|
| `production` / `prod` | `https://api.asaas.com` |
| qualquer outro valor | `https://api-sandbox.asaas.com` |

`base_url` sobrepõe as duas URLs. Mantenha `ASAAS_BASE_URL` ausente ou comentada quando não precisar dela: uma linha em branco (`ASAAS_BASE_URL=`) resulta em string vazia e também sobrepõe o ambiente.

### Autenticação

A API key é enviada no header `access_token` (ou no nome definido em `api_key_header`). O SDK também envia `Accept: application/json`, `Content-Type: application/json` e o `User-Agent` configurado.

## Uso

Os serviços são propriedades readonly de `Asaas\Laravel\AsaasSdk`. O SDK é registrado como singleton no container, e há três formas equivalentes de obtê-lo:

```php
use Asaas\Laravel\AsaasSdk;

$sdk = asaas();
$sdk = app(AsaasSdk::class);
```

Ou por injeção de dependência:

```php
use Asaas\Laravel\AsaasSdk;

class CustomerController
{
    public function __construct(private readonly AsaasSdk $asaas)
    {
    }

    public function index(): array
    {
        return $this->asaas->customer->list(['limit' => 10])->toArray();
    }
}
```

Os serviços individuais (`CustomerService`, `PaymentService` e os demais) **não** estão registrados no container, e `AsaasHttpClient` exige um `AsaasSdkConfig`. Por isso `app(CustomerService::class)` falha: acesse sempre pela propriedade do SDK, por exemplo `asaas()->customer`.

### Clientes

```php
$customers = asaas()->customer;

$page = $customers->list(['offset' => 0, 'limit' => 10, 'email' => 'joao@example.com']);

$customer = $customers->create([
    'name' => 'João Silva',
    'email' => 'joao@example.com',
    'cpfCnpj' => '12345678909',
]);

$customer = $customers->get($customer->id);
$customer = $customers->update($customer->id, ['name' => 'João Silva Atualizado']);

$result = $customers->delete($customer->id);
$result->deleted;
```

### Cobranças

```php
$payments = asaas()->payment;

$page = $payments->list(['customer' => 'cus_xxx', 'status' => 'PENDING', 'limit' => 10]);

$payment = $payments->create([
    'customer' => 'cus_xxx',
    'billingType' => 'BOLETO',
    'value' => 99.90,
    'dueDate' => '2026-12-15',
    'description' => 'Pedido #123',
]);

$payment = $payments->get($payment->id);
$payment = $payments->update($payment->id, ['description' => 'Nova descrição']);
$payments->delete($payment->id);
```

### Múltiplas contas

`AsaasSdk::fromConfig()` cria instâncias adicionais, independentes do singleton, dentro de uma aplicação Laravel. É o caminho para multi-tenant ou para usar a API key de uma subconta:

```php
use Asaas\Laravel\AsaasSdk;

$tenantSdk = AsaasSdk::fromConfig(array_merge(config('asaas'), [
    'api_key' => $tenantApiKey,
]));

$tenantSdk->customer->list(['limit' => 10]);
```

O array aceito por `fromConfig()` usa as mesmas chaves de `config/asaas.php`. Chaves ausentes assumem os padrões da tabela de configuração (ambiente sandbox, timeout de 10000 ms, sem retry).

### Uso fora do Laravel

Não é suportado. O cliente HTTP usa o `Http` facade e as validações de entrada usam o `Validator` facade, então é preciso uma aplicação Laravel inicializada (booted).

## Serviços disponíveis

Cada serviço é uma propriedade readonly de `AsaasSdk` (por exemplo `asaas()->customer`). Todos os serviços expõem apenas `list()`, exceto `customer` e `payment`, que têm CRUD completo.

| Propriedade | Endpoint | Métodos | Na referência oficial | Documentação |
|---|---|---|---|---|
| `accountDocument` | `GET v3/myAccount/documents` | `list` | consta | [AccountDocumentService](docs/services/AccountDocumentService.md) |
| `accountInfo` | `GET v3/myAccount` | `list` | não consta | [AccountInfoService](docs/services/AccountInfoService.md) |
| `anticipation` | `GET v3/anticipations` | `list` | consta | [AnticipationService](docs/services/AnticipationService.md) |
| `bill` | `GET v3/bills` | `list` | não consta | [BillService](docs/services/BillService.md) |
| `chargeback` | `GET v3/chargebacks` | `list` | consta | [ChargebackService](docs/services/ChargebackService.md) |
| `checkout` | `GET v3/checkout` | `list` | não consta | [CheckoutService](docs/services/CheckoutService.md) |
| `creditBureauReport` | `GET v3/creditBureauReport` | `list` | consta | [CreditBureauReportService](docs/services/CreditBureauReportService.md) |
| `creditCard` | `GET v3/creditCard` | `list` | não consta | [CreditCardService](docs/services/CreditCardService.md) |
| `customer` | `v3/customers`, `v3/customers/{id}` | `list`, `create`, `get`, `update`, `delete` | consta | [CustomerService](docs/services/CustomerService.md) |
| `escrowAccount` | `GET v3/escrow` | `list` | não consta | [EscrowAccountService](docs/services/EscrowAccountService.md) |
| `finance` | `GET v3/finances` | `list` | não consta | [FinanceService](docs/services/FinanceService.md) |
| `financialTransaction` | `GET v3/financialTransactions` | `list` | consta | [FinancialTransactionService](docs/services/FinancialTransactionService.md) |
| `fiscalInfo` | `GET v3/fiscalInfo` | `list` | consta | [FiscalInfoService](docs/services/FiscalInfoService.md) |
| `installment` | `GET v3/installments` | `list` | consta | [InstallmentService](docs/services/InstallmentService.md) |
| `invoice` | `GET v3/invoices` | `list` | consta | [InvoiceService](docs/services/InvoiceService.md) |
| `mobilePhoneRecharge` | `GET v3/mobilePhoneRecharge` | `list` | não consta | [MobilePhoneRechargeService](docs/services/MobilePhoneRechargeService.md) |
| `notification` | `GET v3/notifications` | `list` | não consta | [NotificationService](docs/services/NotificationService.md) |
| `paymentDocument` | `GET v3/paymentDocuments` | `list` | não consta | [PaymentDocumentService](docs/services/PaymentDocumentService.md) |
| `paymentDunning` | `GET v3/paymentDunnings` | `list` | consta | [PaymentDunningService](docs/services/PaymentDunningService.md) |
| `paymentLink` | `GET v3/paymentLinks` | `list` | consta | [PaymentLinkService](docs/services/PaymentLinkService.md) |
| `paymentRefund` | `GET v3/payments/refunds` | `list` | não consta | [PaymentRefundService](docs/services/PaymentRefundService.md) |
| `payment` | `v3/payments`, `v3/payments/{id}` | `list`, `create`, `get`, `update`, `delete` | consta | [PaymentService](docs/services/PaymentService.md) |
| `paymentSplit` | `GET v3/paymentSplits` | `list` | não consta | [PaymentSplitService](docs/services/PaymentSplitService.md) |
| `paymentWithSummaryData` | `GET v3/payments/summary` | `list` | não consta | [PaymentWithSummaryDataService](docs/services/PaymentWithSummaryDataService.md) |
| `pix` | `GET v3/pix` | `list` | não consta | [PixService](docs/services/PixService.md) |
| `pixTransaction` | `GET v3/pix/transactions` | `list` | consta | [PixTransactionService](docs/services/PixTransactionService.md) |
| `recurringPix` | `GET v3/recurringPix` | `list` | não consta | [RecurringPixService](docs/services/RecurringPixService.md) |
| `sandboxActions` | `GET v3/sandbox` | `list` | não consta | [SandboxActionsService](docs/services/SandboxActionsService.md) |
| `subaccount` | `GET v3/accounts` | `list` | consta | [SubaccountService](docs/services/SubaccountService.md) |
| `subscription` | `GET v3/subscriptions` | `list` | consta | [SubscriptionService](docs/services/SubscriptionService.md) |
| `transfer` | `GET v3/transfers` | `list` | consta | [TransferService](docs/services/TransferService.md) |
| `webhook` | `GET v3/webhooks` | `list` | consta | [WebhookService](docs/services/WebhookService.md) |

**Atenção:** os 15 serviços marcados como "não consta" chamam um caminho que não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que funcionem; a API pode responder com erro HTTP (por exemplo 404). O documento de cada serviço lista os endpoints oficiais equivalentes.

## Cobertura da API

O SDK implementa **26 de 213 endpoints (12,2%)** da [referência oficial da API Asaas](https://docs.asaas.com/reference), verificada em 2026-11-10. O SDK faz 40 chamadas distintas; 25 constam na referência e correspondem a 26 dos 213 endpoints, pois duas páginas da referência compartilham `POST /v3/payments`. As outras 15 chamadas não constam na referência (veja a tabela acima). A cobertura completa está planejada para a versão 2.0.0.

| Categoria | Implementados / total |
|---|---|
| Clientes | 5 / 7 |
| Cobranças | 6 / 19 |
| Cobranças com dados resumidos | 0 / 10 |
| Documentos de cobranças | 0 / 5 |
| Parcelamentos | 1 / 9 |
| Assinaturas | 1 / 14 |
| Link de pagamentos | 1 / 11 |
| Checkout | 0 / 2 |
| Cartão de crédito | 0 / 3 |
| Antecipações | 1 / 8 |
| Estornos | 0 / 5 |
| Chargeback | 1 / 3 |
| Splits | 0 / 4 |
| Conta Escrow | 0 / 6 |
| Notificações | 0 / 2 |
| Consulta Serasa | 1 / 3 |
| Negativações | 1 / 9 |
| Notas fiscais | 1 / 6 |
| Informações fiscais | 1 / 10 |
| Pix | 0 / 8 |
| Transações Pix | 1 / 5 |
| Pix Automático | 0 / 7 |
| Pix Recorrente | 0 / 5 |
| Transferências | 1 / 5 |
| Pagamento de contas | 0 / 5 |
| Recargas de celular | 0 / 5 |
| Extrato | 1 / 1 |
| Informações financeiras | 0 / 3 |
| Informações e personalização da conta | 0 / 9 |
| Webhooks | 1 / 6 |
| Subcontas Asaas | 1 / 9 |
| Envio de documentos para contas BaaS | 1 / 5 |
| Ações em sandbox | 0 / 3 |
| Registro de Recebíveis | 0 / 1 |

## Respostas e paginação

Os métodos `list()` retornam um model de lista:

| Propriedade ou método | Tipo | Descrição |
|-----------------------|------|-----------|
| `items` | `list<Item>` | Itens da página, já convertidos para o model do recurso. |
| `totalCount` | `?int` | Total de registros informado pela API. |
| `hasMore` | `?bool` | Indica se há mais páginas. |
| `toArray()` | `array` | `['data' => [...], 'totalCount' => ..., 'hasMore' => ...]`, o mesmo formato da API. |

`totalCount` e `hasMore` são `null` quando a API não os envia.

Os models de item expõem:

- `id` (`?string`);
- `data` (`array`): o payload bruto devolvido pela API, com todos os campos;
- `toArray()`: o payload bruto mesclado com as propriedades tipadas que não são nulas;
- propriedades tipadas adicionais quando existem, por exemplo `Customer::$name`, `Customer::$email`, `Customer::$cpfCnpj`, `Customer::$externalReference`, `Payment::$customer`, `Payment::$value` (`?float`) e `Payment::$status`. Para qualquer outro campo, leia `$item->data['campo']`.

Os resultados de remoção (`CustomerDeleteResult`, `PaymentDeleteResult`) expõem `deleted` (`?bool`) e `data`. Eles **não** têm `id`, e `toArray()` retorna apenas o payload bruto.

Paginação com `offset`, `limit` e `hasMore` (`limit` aceita de 1 a 100):

```php
$offset = 0;
$limit = 100;

do {
    $page = asaas()->customer->list(['offset' => $offset, 'limit' => $limit]);

    foreach ($page->items as $customer) {
        echo $customer->id, ' ', $customer->name, PHP_EOL;
    }

    $offset += $limit;
} while ($page->hasMore === true);
```

## Validação

Antes de qualquer chamada HTTP, os métodos que recebem parâmetros validam o array com o `Validator` do Laravel. Se algo for inválido, é lançada `Illuminate\Validation\ValidationException` e nenhuma requisição é enviada:

```php
use Illuminate\Validation\ValidationException;

try {
    asaas()->customer->list(['limit' => 500]);
} catch (ValidationException $e) {
    $e->errors();
}
```

Três comportamentos que costumam surpreender:

- **Chaves sem regra são descartadas em silêncio.** O SDK envia somente os campos que têm regra de validação. Qualquer outra chave do array é removida sem erro. Por exemplo, em um serviço cuja listagem valida só `offset` e `limit`, um filtro como `['status' => 'ACTIVE']` não chega à API.
- **Nas regras atuais, todos os campos são `nullable`.** A validação local não exige nenhum campo. Campos obrigatórios ausentes só são detectados pela API, que responde com HTTP 400 (veja "Erros").
- **Os parâmetros de rota não são validados.** O `$id` de `get()`, `update()` e `delete()` é inserido no path como recebido.

As regras de cada serviço ficam nos documentos em [docs/services](docs/services).

## Erros

| Situação | Exceção | Detalhes |
|----------|---------|----------|
| Entrada inválida | `Illuminate\Validation\ValidationException` | Lançada antes da chamada HTTP. |
| HTTP 400 | `Asaas\Laravel\Exceptions\ErrorResponseDtoException` | `getErrors()` retorna `errors` da resposta; `errorResponse` guarda o corpo decodificado. |
| Qualquer outro status não 2xx | `Asaas\Laravel\Exceptions\ApiError` | `statusCode` e `response` (`Illuminate\Http\Client\Response`). |
| Falha de rede ou timeout | `Illuminate\Http\Client\ConnectionException` | Não é convertida em `ApiError`. Veja "Retry e timeout". |

A mensagem de `ApiError` e de `ErrorResponseDtoException` vem de `errors[0].description` da resposta; se ela não existir, é o corpo bruto. `ErrorResponseDtoException` estende `ApiError`, então capture-a **antes** de `ApiError`:

```php
use Asaas\Laravel\Exceptions\ApiError;
use Asaas\Laravel\Exceptions\ErrorResponseDtoException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

try {
    $customer = asaas()->customer->create($data);
} catch (ValidationException $e) {
    $messages = $e->errors();
} catch (ErrorResponseDtoException $e) {
    foreach ($e->getErrors() as $error) {
        logger()->warning(($error['code'] ?? 'unknown') . ': ' . ($error['description'] ?? ''));
    }
} catch (ApiError $e) {
    logger()->error('Asaas error', [
        'status' => $e->statusCode,
        'body' => $e->response?->json(),
    ]);
} catch (ConnectionException $e) {
    logger()->error('Asaas unreachable', ['message' => $e->getMessage()]);
}
```

## Retry e timeout

O SDK repassa `retry.max_retries` e `retry.initial_delay` para o `PendingRequest::retry()` do Laravel. O comportamento real é:

- **`max_retries` é o total de tentativas**, incluindo a primeira. Com o padrão `1` não há retry; com `3`, são até duas novas tentativas.
- **A espera entre tentativas é fixa**: `initial_delay` milissegundos. Não há backoff exponencial. `max_delay`, `backoff_factor` e `jitter` são lidos para o objeto de configuração, mas o cliente HTTP não os usa: atualmente são ignorados. O cabeçalho `Retry-After` também não é considerado.
- **Respostas com erro** só são repetidas quando o status está em `retry.status_codes` (padrão: 408, 429, 500, 502, 503 e 504) **e** o método está em `retry.methods` (padrão: GET, POST, PUT, PATCH e DELETE). A comparação do método diferencia maiúsculas de minúsculas.
- **Falhas de conexão** (timeout, DNS, falha de conexão ou de TLS) são repetidas para os métodos elegíveis, sem checagem de status.
- **O `timeout` vale por tentativa.** O tempo máximo de uma chamada é aproximadamente `timeout * max_retries` mais as esperas.
- **Ao esgotar as tentativas**, a última resposta com erro é mapeada normalmente (`ApiError` ou `ErrorResponseDtoException`). Uma falha de conexão lança `Illuminate\Http\Client\ConnectionException`.

O mínimo de Laravel 9.7 existe porque as versões 9.x anteriores não repetem respostas com falha.

Por padrão `POST` é elegível e o SDK não envia chave de idempotência. Se aumentar `max_retries`, avalie remover `POST` de `retry.methods` para evitar operações duplicadas em caso de erro 5xx.

Exemplo para até 3 tentativas com 300 ms de espera:

```env
ASAAS_RETRY_MAX=3
ASAAS_RETRY_INITIAL_DELAY=300
```

## Testando sua aplicação

Use `Http::fake` com o host do ambiente configurado: `api-sandbox.asaas.com/*` em sandbox e `api.asaas.com/*` em produção. O SDK é um singleton do container: defina a configuração **antes** da primeira resolução de `asaas()`. Em testes de feature do Laravel o container é recriado a cada teste, então basta chamar `config([...])` no início do teste.

```php
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

public function test_creates_customer(): void
{
    config(['asaas.api_key' => 'test_key', 'asaas.environment' => 'sandbox']);

    Http::fake([
        'api-sandbox.asaas.com/*' => Http::response(['id' => 'cus_123', 'name' => 'João Silva'], 200),
    ]);

    $customer = asaas()->customer->create(['name' => 'João Silva']);

    $this->assertSame('cus_123', $customer->id);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api-sandbox.asaas.com/v3/customers'
        && $request->hasHeader('access_token', 'test_key')
        && $request['name'] === 'João Silva');
}
```

Para simular um erro da API:

```php
use Asaas\Laravel\Exceptions\ErrorResponseDtoException;

Http::fake([
    'api-sandbox.asaas.com/*' => Http::response([
        'errors' => [['code' => 'invalid_object', 'description' => 'Invalid customer']],
    ], 400),
]);

$this->expectException(ErrorResponseDtoException::class);

asaas()->customer->create(['name' => 'João Silva']);
```

Observações:

- Se mudar a configuração depois da primeira resolução no mesmo teste, chame `app()->forgetInstance(\Asaas\Laravel\AsaasSdk::class)` antes de usar `asaas()` de novo.
- `AsaasSdk` e `AsaasHttpClient` são classes `final`, então não podem ser mockadas com Mockery. Prefira `Http::fake`.
- `Http::preventStrayRequests()` faz o teste falhar se alguma requisição não for coberta pelo fake.

## Desenvolvimento

```bash
composer install
vendor/bin/phpunit
```

Os testes ficam em `tests/` e usam PHPUnit com Orchestra Testbench.

O design do SDK espelha o [SDK Java oficial do Asaas](https://github.com/asaasdev/asaas-api-sdk-java): serviços por recurso sobre uma classe base, requests de validação internos, models de resposta e mapeamento de erros por status HTTP.

## Licença

MIT.
