# Asaas API SDK Laravel

SDK Laravel para a [API Asaas v3](https://docs.asaas.com/reference): cobertura de **213 de 213 endpoints oficiais**, em 34 serviços, sobre o `Http` facade do Laravel. Validação de entrada antes de cada chamada, models de resposta e erros tipados. A API pública trabalha com arrays.

Versão atual: **2.0.0**. Vindo da 1.x? Leia o [UPGRADE.md](UPGRADE.md).

## Compatibilidade

| Laravel | PHP suportado | PHP oficial do Laravel |
|---------|---------------|------------------------|
| 9 (>= 9.7) | 8.1 a 8.5 | 8.0 a 8.2 |
| 10 | 8.1 a 8.5 | 8.1 a 8.3 |
| 11 | 8.2 a 8.5 | 8.2 a 8.4 |
| 12 | 8.2 a 8.5 | 8.2 a 8.5 |
| 13 | 8.3 a 8.5 | 8.3 a 8.5 |

- As combinações fora da faixa oficial de PHP do Laravel são cobertas pela nossa CI, mas não são suportadas pelo próprio Laravel.
- Laravel 9, 10 e 11 não recebem mais correções de segurança do Laravel. O Composer 2.9 bloqueia essas versões por padrão; para instalar, defina `COMPOSER_NO_SECURITY_BLOCKING=1`.
- O pacote exige `guzzlehttp/guzzle` `^7.2`. O Laravel 9 e o 10 apenas sugerem o Guzzle, então ele é instalado junto com o SDK.
- O mínimo de Laravel 9.7 existe porque as versões 9.x anteriores não fazem retry de respostas com falha (veja "Retry e timeout").

## Instalação

```bash
composer require luquinhasbrito/asaas-api-sdk-laravel
```

O pacote registra `Asaas\Laravel\AsaasServiceProvider` pelo package auto-discovery, e o helper global `asaas()` é carregado automaticamente. O provider faz `mergeConfigFrom`, então **publicar a configuração é opcional**: basta definir as variáveis no `.env`. Publique apenas se quiser editar chaves que não têm variável de ambiente (como `retry.methods`):

```bash
php artisan vendor:publish --tag=asaas-config
```

## Configuração

```env
ASAAS_API_KEY=your_api_key
ASAAS_ENV=sandbox
ASAAS_TIMEOUT=10000
ASAAS_RETRY_MAX=1
# ASAAS_BASE_URL=https://api-sandbox.asaas.com
# ASAAS_USER_AGENT=my-app/1.0
# ASAAS_RETRY_INITIAL_DELAY=150
```

Chaves de `config/asaas.php`:

| Chave | Variável de ambiente | Padrão | Descrição |
|-------|----------------------|--------|-----------|
| `api_key` | `ASAAS_API_KEY` | `''` | Chave de API enviada no header de autenticação. Se vazia, nenhum header de autenticação é enviado. |
| `environment` | `ASAAS_ENV` | `sandbox` | `production` ou `prod` seleciona produção; **qualquer outro valor** seleciona sandbox, inclusive grafias erradas como `producao`. |
| `base_url` | `ASAAS_BASE_URL` | `null` | URL base opcional. Quando definida, sobrepõe a URL do ambiente. |
| `timeout` | `ASAAS_TIMEOUT` | `10000` | Timeout em **milissegundos**, aplicado a cada tentativa. |
| `user_agent` | `ASAAS_USER_AGENT` | `asaas-api-sdk-laravel/1.0` | Valor do header `User-Agent`. |
| `retry.max_retries` | `ASAAS_RETRY_MAX` | `1` | Número **total de tentativas**, incluindo a primeira. O padrão `1` significa nenhum retry. |
| `retry.initial_delay` | `ASAAS_RETRY_INITIAL_DELAY` | `150` | Espera fixa, em milissegundos, entre tentativas. |
| `retry.max_delay` | `ASAAS_RETRY_MAX_DELAY` | `1000` | Lido, mas ignorado pelo cliente HTTP. |
| `retry.backoff_factor` | `ASAAS_RETRY_BACKOFF_FACTOR` | `2` | Lido, mas ignorado pelo cliente HTTP. |
| `retry.jitter` | `ASAAS_RETRY_JITTER` | `150` | Lido, mas ignorado pelo cliente HTTP. |
| `retry.status_codes` | sem variável | `[408, 429, 500, 502, 503, 504]` | Status HTTP que disparam nova tentativa. |
| `retry.methods` | sem variável | `['GET', 'PUT', 'DELETE']` | Métodos elegíveis para nova tentativa, em maiúsculas. `POST` não está na lista de propósito (veja "Retry e timeout"). |
| `api_key_header` | sem variável | `access_token` | Opcional. Nome do header da API key. Não consta no arquivo publicado: para alterá-lo, adicione a chave ao seu `config/asaas.php`. |

| Ambiente | URL base |
|----------|----------|
| `production` / `prod` | `https://api.asaas.com` |
| qualquer outro valor | `https://api-sandbox.asaas.com` |

`base_url` sobrepõe as duas URLs. Mantenha `ASAAS_BASE_URL` ausente ou comentada quando não precisar dela: `ASAAS_BASE_URL=` em branco resulta em string vazia e também sobrepõe o ambiente.

A API key vai no header `access_token`. O SDK também envia `Accept: application/json` (nos endpoints de PDF, `application/pdf, application/json`) e o `User-Agent` configurado.

## Uso rápido

Os serviços são propriedades readonly de `Asaas\Laravel\AsaasSdk`, registrado como singleton:

```php
use Asaas\Laravel\AsaasSdk;

$sdk = asaas();                  // helper
$sdk = app(AsaasSdk::class);     // container
```

Os serviços individuais (`CustomerService`, `PaymentService` etc.) **não** estão registrados no container: acesse sempre pela propriedade, por exemplo `asaas()->customer`. Injetar `AsaasSdk` no construtor também funciona.

### Cliente

```php
$customer = asaas()->customer->create([
    'name' => 'João Silva',
    'email' => 'joao@example.com',
    'cpfCnpj' => '12345678909',
]);

asaas()->customer->update($customer->id, ['name' => 'João S. Silva']);
$result = asaas()->customer->delete($customer->id);   // CustomerDeleteResult
```

### Cobrança com Pix

```php
$payment = asaas()->payment->create([
    'customer' => $customer->id,
    'billingType' => 'PIX',
    'value' => 99.90,
    'dueDate' => '2026-12-15',
    'description' => 'Pedido #123',
]);

$qrCode = asaas()->payment->getPixQrCode($payment->id);   // GenericResource
$qrCode->data['payload'];        // copia e cola
$qrCode->data['encodedImage'];   // imagem em base64
```

### Cobrança com boleto

```php
$payment = asaas()->payment->create([
    'customer' => $customer->id,
    'billingType' => 'BOLETO',
    'value' => 99.90,
    'dueDate' => '2026-12-15',
    'discount' => ['value' => 5, 'dueDateLimitDays' => 3, 'type' => 'PERCENTAGE'],
    'fine' => ['value' => 2, 'type' => 'PERCENTAGE'],
    'interest' => ['value' => 1],
]);

$line = asaas()->payment->getIdentificationField($payment->id);
$line->data['identificationField'];
```

`discount`, `fine` e `interest` são objetos, conforme o schema oficial. Com cartão, `creditCard` e `creditCardHolderInfo` são dispensados quando `creditCardToken` é enviado (veja [`payWithCreditCard`](docs/services/PaymentService.md)).

### Listagem com filtros e paginação

Os filtros usam as chaves oficiais da API, inclusive as com colchetes:

```php
$offset = 0;
$limit = 100;   // de 1 a 100

do {
    $page = asaas()->payment->list([
        'customer' => 'cus_xxx',
        'status' => 'PENDING',
        'dueDate[ge]' => '2026-12-01',
        'dueDate[le]' => '2026-12-31',
        'offset' => $offset,
        'limit' => $limit,
    ]);

    foreach ($page->items as $payment) {
        echo $payment->id, ' ', $payment->value, PHP_EOL;
    }

    $offset += $limit;
} while ($page->hasMore === true);
```

A validação de query é **estrita**: uma chave desconhecida (por exemplo `paymentDateGe`) lança `Illuminate\Validation\ValidationException` antes da chamada HTTP. Isso evita o risco de a Asaas ignorar o filtro e devolver a lista inteira. `offset` aceita 0 ou mais e `limit` de 1 a 100.

### Assinatura

```php
$subscription = asaas()->subscription->create([
    'customer' => $customer->id,
    'billingType' => 'BOLETO',
    'value' => 49.90,
    'nextDueDate' => '2026-12-10',
    'cycle' => 'MONTHLY',
    'description' => 'Plano mensal',
]);

$payments = asaas()->subscription->listPayments($subscription->id, ['limit' => 10]);
$carnet = asaas()->subscription->getPaymentBook($subscription->id);   // string com o PDF
```

### Webhook

```php
$webhook = asaas()->webhook->create([
    'name' => 'Pagamentos',
    'url' => 'https://example.com/asaas/webhook',
    'email' => 'dev@example.com',
    'enabled' => true,
    'interrupted' => false,
    'sendType' => 'SEQUENTIALLY',
    'authToken' => bin2hex(random_bytes(32)),
    'events' => ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_OVERDUE'],
]);
```

### Upload de arquivos

Campos de arquivo (multipart) aceitam `SplFileInfo` (inclusive `UploadedFile`) ou stream resource. Uma string nunca é lida como caminho de arquivo:

```php
asaas()->paymentDocument->create($payment->id, [
    'type' => 'INVOICE',
    'availableAfterPayment' => true,
    'file' => new \SplFileInfo(storage_path('app/invoice.pdf')),   // ou $request->file('file')
]);
```

### Múltiplas contas

`AsaasSdk::fromConfig()` cria instâncias independentes do singleton, útil para multi-tenant ou para a API key de uma subconta:

```php
$tenantSdk = AsaasSdk::fromConfig(array_merge(config('asaas'), [
    'api_key' => $tenantApiKey,
]));

$tenantSdk->customer->list(['limit' => 10]);
```

O array usa as mesmas chaves de `config/asaas.php`; chaves ausentes assumem os padrões da tabela de configuração.

### Uso fora do Laravel

Não é suportado: o cliente HTTP usa o `Http` facade e a validação usa o `Validator` facade, então é preciso uma aplicação Laravel inicializada.

## Serviços

Cada serviço é uma propriedade de `AsaasSdk`. O índice gerado está em [docs/README-services.md](docs/README-services.md), e cada página em `docs/services/` lista método, verbo, caminho, parâmetros e retorno.

| Propriedade | Classe | Categoria | Métodos |
|---|---|---|---|
| `customer` | [CustomerService](docs/services/CustomerService.md) | Clientes | 7 |
| `payment` | [PaymentService](docs/services/PaymentService.md) | Cobranças | 19 |
| `paymentWithSummaryData` | [PaymentWithSummaryDataService](docs/services/PaymentWithSummaryDataService.md) | Cobranças com dados resumidos | 10 |
| `paymentDocument` | [PaymentDocumentService](docs/services/PaymentDocumentService.md) | Documentos de cobranças | 5 |
| `installment` | [InstallmentService](docs/services/InstallmentService.md) | Parcelamentos | 9 |
| `subscription` | [SubscriptionService](docs/services/SubscriptionService.md) | Assinaturas | 14 |
| `paymentLink` | [PaymentLinkService](docs/services/PaymentLinkService.md) | Link de pagamentos | 11 |
| `checkout` | [CheckoutService](docs/services/CheckoutService.md) | Checkout | 2 |
| `creditCard` | [CreditCardService](docs/services/CreditCardService.md) | Cartão de crédito | 3 |
| `anticipation` | [AnticipationService](docs/services/AnticipationService.md) | Antecipações | 8 |
| `paymentRefund` | [PaymentRefundService](docs/services/PaymentRefundService.md) | Estornos | 5 |
| `chargeback` | [ChargebackService](docs/services/ChargebackService.md) | Chargeback | 3 |
| `paymentSplit` | [PaymentSplitService](docs/services/PaymentSplitService.md) | Splits | 4 |
| `escrowAccount` | [EscrowAccountService](docs/services/EscrowAccountService.md) | Conta Escrow | 6 |
| `notification` | [NotificationService](docs/services/NotificationService.md) | Notificações | 2 |
| `creditBureauReport` | [CreditBureauReportService](docs/services/CreditBureauReportService.md) | Consulta Serasa | 3 |
| `paymentDunning` | [PaymentDunningService](docs/services/PaymentDunningService.md) | Negativações | 9 |
| `invoice` | [InvoiceService](docs/services/InvoiceService.md) | Notas fiscais | 6 |
| `fiscalInfo` | [FiscalInfoService](docs/services/FiscalInfoService.md) | Informações fiscais | 10 |
| `pix` | [PixService](docs/services/PixService.md) | Pix | 8 |
| `pixTransaction` | [PixTransactionService](docs/services/PixTransactionService.md) | Transações Pix | 5 |
| `pixAutomatic` | [PixAutomaticService](docs/services/PixAutomaticService.md) | Pix Automático | 7 |
| `recurringPix` | [RecurringPixService](docs/services/RecurringPixService.md) | Pix Recorrente | 5 |
| `transfer` | [TransferService](docs/services/TransferService.md) | Transferências | 5 |
| `bill` | [BillService](docs/services/BillService.md) | Pagamento de contas | 5 |
| `mobilePhoneRecharge` | [MobilePhoneRechargeService](docs/services/MobilePhoneRechargeService.md) | Recargas de celular | 5 |
| `financialTransaction` | [FinancialTransactionService](docs/services/FinancialTransactionService.md) | Extrato | 1 |
| `finance` | [FinanceService](docs/services/FinanceService.md) | Informações financeiras | 3 |
| `accountInfo` | [AccountInfoService](docs/services/AccountInfoService.md) | Informações e personalização da conta | 9 |
| `webhook` | [WebhookService](docs/services/WebhookService.md) | Webhooks | 6 |
| `subaccount` | [SubaccountService](docs/services/SubaccountService.md) | Subcontas Asaas | 9 |
| `accountDocument` | [AccountDocumentService](docs/services/AccountDocumentService.md) | Envio de documentos para contas BaaS | 5 |
| `sandboxActions` | [SandboxActionsService](docs/services/SandboxActionsService.md) | Ações em sandbox | 3 |
| `receivableRegistration` | [ReceivableRegistrationService](docs/services/ReceivableRegistrationService.md) | Registro de Recebíveis | 1 |

Assinatura dos métodos: um `string` por parâmetro de rota (`$id`, `$documentId` etc.), depois `array $data` quando há corpo e `array $params = []` quando há query ou o método é uma listagem. Exemplo: `asaas()->paymentDocument->get($paymentId, $documentId)`.

## Cobertura da API

O SDK cobre **213 de 213 endpoints (100%)** da [referência oficial da API Asaas](https://docs.asaas.com/reference). Os 34 serviços, as classes de validação, os models novos, os testes e a documentação em `docs/services/` são **gerados** a partir do mapa `tools/endpoint-map.json`, que por sua vez é derivado da especificação oficial (`tools/asaas-openapi.json`). Quando a API ganha endpoints, o mapa é atualizado e o código é regenerado (veja "Desenvolvimento").

## Respostas e models

| Retorno | Quando |
|---------|--------|
| Model do recurso (`Customer`, `Payment`, `Subscription` etc.) | Recursos com model próprio. |
| `Asaas\Laravel\Models\GenericResource` | Respostas sem model próprio (status, QR code, limites, configurações). |
| `Asaas\Laravel\Models\GenericList` | Listas de itens avulsos (tabelas de códigos fiscais, carteiras, histórico). |
| `Asaas\Laravel\Models\DeleteResult` | Remoções que devolvem `{deleted, id}`. |
| `CustomerDeleteResult`, `PaymentDeleteResult` | Remoção de cliente e de cobrança. |
| `string` | Endpoints de PDF (por exemplo `getPaymentBook`): corpo bruto. |
| `void` | Respostas 204 (`webhook->removeBackoff`, `subaccount->deleteAccessToken`, `subaccount->resendActivationLink`). |

Os models de item expõem `data` (payload bruto completo) e `toArray()`; os recursos com identificador, `GenericResource` e `DeleteResult` também têm `id`. Os models de lista expõem `items`, `totalCount` e `hasMore` (`null` quando a API não envia). Campos sem propriedade tipada ficam em `$item->data['campo']`.

## Validação

Antes de qualquer chamada HTTP, o SDK valida a entrada com o `Validator` do Laravel e lança `Illuminate\Validation\ValidationException`, sem enviar requisição:

- **Corpo**: campos obrigatórios do schema oficial são exigidos e tipos/enums são checados. Campos desconhecidos **são enviados** como estão, para que campos novos da API funcionem antes de uma atualização do SDK.
- **Query**: estrita. Chaves sem regra são rejeitadas (mensagem do erro: `O parâmetro de consulta "x" não é suportado.`).
- **Rota**: os parâmetros de rota são codificados com `rawurlencode`, não validados.
- **Booleanos** em query viram `true`/`false`.

```php
use Illuminate\Validation\ValidationException;

try {
    asaas()->customer->list(['limit' => 500]);
} catch (ValidationException $e) {
    $e->errors();   // ['limit' => [...]]
}
```

## Erros

| Situação | Exceção | Detalhes |
|----------|---------|----------|
| Entrada inválida | `Illuminate\Validation\ValidationException` | Lançada antes da chamada HTTP. |
| HTTP 400 | `Asaas\Laravel\Exceptions\ErrorResponseDtoException` | `getErrors()` retorna `errors` da resposta; `errorResponse` guarda o corpo decodificado. |
| Qualquer outro status não 2xx | `Asaas\Laravel\Exceptions\ApiError` | `statusCode` e `response` (`Illuminate\Http\Client\Response`). |
| Falha de rede ou timeout | `Illuminate\Http\Client\ConnectionException` | Não é convertida em `ApiError`. |

A mensagem vem de `errors[0].description` da resposta; se não existir, é o corpo bruto. `ErrorResponseDtoException` estende `ApiError`, então capture-a **antes**:

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
    logger()->error('Asaas error', ['status' => $e->statusCode, 'body' => $e->response?->json()]);
} catch (ConnectionException $e) {
    logger()->error('Asaas unreachable', ['message' => $e->getMessage()]);
}
```

## Retry e timeout

O SDK repassa `retry.max_retries` e `retry.initial_delay` para o `PendingRequest::retry()` do Laravel:

- `max_retries` é o total de tentativas, incluindo a primeira. Com `1` não há retry; com `3`, são até duas novas tentativas.
- A espera é fixa (`initial_delay` ms), sem backoff exponencial e sem considerar `Retry-After`.
- Respostas com erro só são repetidas quando o status está em `retry.status_codes` **e** o método está em `retry.methods`. Falhas de conexão (timeout, DNS, TLS) são repetidas para os métodos elegíveis.
- O `timeout` vale por tentativa: o tempo máximo é aproximadamente `timeout * max_retries` mais as esperas.
- Ao esgotar as tentativas, a última resposta é mapeada normalmente (`ApiError` ou `ErrorResponseDtoException`); falha de conexão lança `ConnectionException`.

**`POST` não é repetido por padrão** (os métodos elegíveis são `GET`, `PUT` e `DELETE`). Um `POST` repetido após um erro 5xx ou timeout pode duplicar cobranças ou transferências, porque o SDK não envia chave de idempotência. Para habilitar por sua conta e risco, publique a configuração e edite `retry.methods`:

```php
// config/asaas.php
'retry' => [
    'max_retries' => 3,
    'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
],
```

> **Atenção:** só habilite `POST` se você deduplica no seu lado (por exemplo com `externalReference` único e consulta prévia). Nunca para criação de cobranças, transferências ou pagamentos sem essa proteção.

## Testando sua aplicação

Use `Http::fake` com o host do ambiente configurado: `api-sandbox.asaas.com/*` em sandbox e `api.asaas.com/*` em produção. O SDK é singleton: defina a configuração **antes** da primeira resolução de `asaas()`. Em testes de feature o container é recriado a cada teste, então basta chamar `config([...])` no início.

```php
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

public function test_creates_customer(): void
{
    config(['asaas.api_key' => 'test_key', 'asaas.environment' => 'sandbox']);

    Http::fake([
        'api-sandbox.asaas.com/*' => Http::response(['id' => 'cus_123', 'name' => 'João Silva'], 200),
    ]);

    $customer = asaas()->customer->create(['name' => 'João Silva', 'cpfCnpj' => '12345678909']);

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

asaas()->customer->create(['name' => 'João Silva', 'cpfCnpj' => '12345678909']);
```

- Se mudar a configuração depois da primeira resolução no mesmo teste, chame `app()->forgetInstance(\Asaas\Laravel\AsaasSdk::class)`.
- `AsaasSdk` e `AsaasHttpClient` são `final`: não podem ser mockadas com Mockery. Prefira `Http::fake`.
- `Http::preventStrayRequests()` faz o teste falhar se alguma requisição não estiver coberta pelo fake.

## Uso com IA

O pacote traz uma skill para agentes de IA em [`resources/boost/skills/asaas-sdk-laravel/SKILL.md`](resources/boost/skills/asaas-sdk-laravel/SKILL.md). O [Laravel Boost](https://laravel.com/docs/boost) detecta skills de pacotes instalados, então ela é carregada automaticamente em projetos que usam o Boost. Para receitas de uso passo a passo, veja o [docs/ai-playbook.md](docs/ai-playbook.md).

## Desenvolvimento

```bash
composer install
vendor/bin/phpunit
```

O código de `src/Services`, `src/Http/Requests/*`, parte dos models, `tests/Services` e `docs/services` é gerado. **Não edite esses arquivos à mão**: altere `tools/endpoint-map.json` e regenere.

```bash
composer generate         # php tools/generator/generate.php
composer generate:check   # php tools/generator/generate.php --check --strict
```

`generate:check` regenera em memória e falha (exit 1) se algum arquivo gerado divergir do mapa ou se restar uma classe de request que o mapa não referencia; use-o na CI. Para atualizar a especificação oficial:

```bash
php tools/sync-spec.php
```

O script baixa as páginas de [docs.asaas.com](https://docs.asaas.com), atualiza `tools/asaas-openapi.json` e `tools/doc-pages.json` e informa o que mudou em relação ao mapa. Depois, edite `tools/endpoint-map.json` para operações novas ou removidas e rode `composer generate`.

## Migração da 1.x

A 2.0.0 tem mudanças incompatíveis (métodos removidos, assinaturas, filtros e retry). Veja o [UPGRADE.md](UPGRADE.md).

## Licença

MIT.
