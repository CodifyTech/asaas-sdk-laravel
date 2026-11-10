# Guia de migração: 1.x para 2.0.0

A 2.0.0 leva o SDK de 26 para **213 endpoints oficiais** (213 de 213) e corrige chamadas da 1.x que apontavam para caminhos inexistentes. Por isso há mudanças incompatíveis. Requisitos de PHP e Laravel não mudaram (PHP 8.1 a 8.5, Laravel 9.7 a 13).

```bash
composer require luquinhasbrito/asaas-api-sdk-laravel:^2.0
```

## Checklist

1. Troque as chamadas a métodos removidos (seção 1).
2. Ajuste `paymentDocument->list()` e `paymentRefund->list()` (seção 2).
3. Troque filtros `paymentDateGe` por `paymentDate[ge]` e remova chaves desconhecidas (seção 3).
4. Envie `discount`, `fine` e `interest` como objetos (seção 4).
5. Passe arquivos como `SplFileInfo` ou stream (seção 5).
6. Revise tipos de retorno de PDF e 204 (seção 6).
7. Se dependia de retry em `POST`, revise (seção 8).
8. Remova imports de classes deletadas (seção 10).

## 1. Métodos removidos

Estes métodos chamavam caminhos que **não existem** na API Asaas, então sempre falhavam (ou devolviam resultado vazio). Foram removidos sem substituto:

| Removido |
|----------|
| `accountInfo->list()` |
| `checkout->list()` |
| `creditCard->list()` |
| `escrowAccount->list()` |
| `finance->list()` |
| `sandboxActions->list()` |

Os endpoints reais desses serviços agora existem com nomes próprios. Consulte [docs/README-services.md](docs/README-services.md).

### Removidos com substituto

| 1.x | 2.0.0 |
|-----|-------|
| `notification->list()` | `customer->listNotifications(string $id, array $params = [])` |
| `pix->list()` | `pix->listAddressKeys(array $params = [])` |
| `paymentSplit->list()` | `paymentSplit->listPaid()` ou `paymentSplit->listReceived()` |
| `fiscalInfo->list()` | `fiscalInfo->get()` |

```php
// 1.x
$page = asaas()->notification->list(['limit' => 10]);
$page = asaas()->pix->list();
$page = asaas()->paymentSplit->list();
$page = asaas()->fiscalInfo->list();   // sempre devolvia uma FiscalInfoList vazia

// 2.0.0
$page = asaas()->customer->listNotifications($customerId, ['limit' => 10]);   // NotificationList
$page = asaas()->pix->listAddressKeys();                                       // PixAddressKeyList
$page = asaas()->paymentSplit->listPaid();                                     // splits que você pagou
$page = asaas()->paymentSplit->listReceived();                                 // splits que você recebeu
$info = asaas()->fiscalInfo->get();                                            // um único FiscalInfo
```

`fiscalInfo->get()` retorna um objeto, não uma lista: o endpoint devolve as informações fiscais da conta.

### Métodos que mudaram de caminho (mesma assinatura)

`bill->list()`, `mobilePhoneRecharge->list()`, `paymentWithSummaryData->list()` e `recurringPix->list()` mantêm a assinatura, mas agora chamam os caminhos oficiais (`/v3/bill`, `/v3/mobilePhoneRecharges`, `/v3/lean/payments` e `/v3/pix/transactions/recurrings`). Nenhuma alteração de código é necessária, mas o resultado agora vem da API de verdade.

## 2. Mudanças de assinatura

Listar documentos e estornos de uma cobrança exige o id da cobrança:

```php
// 1.x
asaas()->paymentDocument->list(['limit' => 10]);
asaas()->paymentRefund->list(['limit' => 10]);

// 2.0.0
asaas()->paymentDocument->list(string $paymentId, array $params = []);
asaas()->paymentRefund->list(string $paymentId, array $params = []);

asaas()->paymentDocument->list('pay_xxx', ['limit' => 10]);
asaas()->paymentRefund->list('pay_xxx');
```

Essas são as únicas mudanças de assinatura em métodos existentes. As demais (`customer`, `payment`, `installment->list`, `webhook->list` etc.) mantêm a assinatura. Em métodos novos, a ordem dos parâmetros é: um `string` por parâmetro de rota, depois `array $data` (quando há corpo) e `array $params = []` (quando há query).

## 3. Filtros de listagem e query estrita

Os filtros usam as **chaves oficiais da API**, com colchetes:

```php
// 1.x
asaas()->payment->list(['paymentDateGe' => '2026-12-01']);

// 2.0.0
asaas()->payment->list(['paymentDate[ge]' => '2026-12-01', 'paymentDate[le]' => '2026-12-31']);
```

Na 1.x, chaves sem regra de validação eram **descartadas em silêncio**: a listagem de vários serviços validava só `offset` e `limit`, então outros filtros nunca chegavam à API. Na 2.0.0 todos os filtros oficiais são aceitos e a validação de query é **estrita**: uma chave desconhecida lança `Illuminate\Validation\ValidationException`, antes de qualquer chamada HTTP. A Asaas ignora filtros desconhecidos e devolve a lista sem filtrar, o que poderia expor dados.

```php
use Illuminate\Validation\ValidationException;

try {
    asaas()->payment->list(['paymentDateGe' => '2026-12-01']);
} catch (ValidationException $e) {
    $e->errors();   // ['paymentDateGe' => ['O parâmetro de consulta "paymentDateGe" não é suportado.']]
}
```

Revise todas as chamadas `list()` que passavam chaves que antes eram ignoradas. As chaves válidas de cada listagem estão em `docs/services/`. Observação: a especificação oficial de `invoice->list()` usa `effectiveDate[Ge]` e `effectiveDate[Le]` com maiúscula, e o SDK mantém as chaves como a especificação.

Também mudaram:

- `offset` aceita 0 ou mais e `limit` de 1 a 100.
- Booleanos em query são enviados como `true`/`false` (antes viravam `1`).

## 4. Validação de corpo e objetos de desconto, multa e juros

Na 1.x todas as regras eram `nullable`: campos obrigatórios só eram detectados pela API (HTTP 400). Na 2.0.0 os campos obrigatórios do schema oficial são exigidos localmente e lançam `ValidationException`. Campos desconhecidos no **corpo** continuam passando para a API (o corpo é enviado como recebido), para que campos novos funcionem sem atualizar o SDK.

`discount`, `fine` e `interest` de cobranças (e assinaturas) são **objetos**, conforme o schema oficial. Na 1.x eram números (`'discount' => 10`), formato que a API não aceita:

```php
asaas()->payment->create([
    'customer' => 'cus_xxx',
    'billingType' => 'BOLETO',
    'value' => 100,
    'dueDate' => '2026-12-15',
    'discount' => ['value' => 10, 'dueDateLimitDays' => 5, 'type' => 'FIXED'],   // FIXED | PERCENTAGE
    'fine' => ['value' => 2, 'type' => 'PERCENTAGE'],
    'interest' => ['value' => 1],
]);
```

Em pagamento com cartão, `creditCard` e `creditCardHolderInfo` **não são obrigatórios** quando `creditCardToken` é enviado:

```php
asaas()->payment->payWithCreditCard('pay_xxx', ['creditCardToken' => $token]);
```

## 5. Upload de arquivos (multipart)

Campos de arquivo aceitam `\SplFileInfo` (inclusive `Illuminate\Http\UploadedFile`), um stream resource, ou uma lista deles. Uma **string simples nunca é lida como caminho de arquivo**, para que um campo vindo de uma requisição (`"file": "/etc/passwd"`) não suba um arquivo local. Uma string nesse campo lança `ValidationException`.

```php
asaas()->paymentDocument->create('pay_xxx', [
    'type' => 'INVOICE',
    'availableAfterPayment' => true,
    'file' => new \SplFileInfo(storage_path('app/invoice.pdf')),   // não: '/caminho/invoice.pdf'
]);
```

O arquivo é lido inteiro em memória antes do envio. Multipart só é suportado com `POST`.

## 6. Tipos de retorno e novos models

- Endpoints de PDF retornam `string` (corpo bruto): `installment->getPaymentBook()` e `subscription->getPaymentBook()`.
- Endpoints com resposta 204 retornam `void`: `webhook->removeBackoff()`, `subaccount->deleteAccessToken()` e `subaccount->resendActivationLink()`.
- Novos models genéricos em `Asaas\Laravel\Models`:
  - `GenericResource` (`?string $id`, `array $data`): respostas sem model próprio (status, QR code, limites, configurações);
  - `GenericList` (`items`, `totalCount`, `hasMore`, `data`): listas de itens avulsos, com `items` como arrays brutos;
  - `DeleteResult` (`?bool $deleted`, `?string $id`, `array $data`): remoções que devolvem `{deleted, id}`.
- `CustomerDeleteResult` e `PaymentDeleteResult` continuam existindo.
- Novos models nomeados: `PaymentLinkImage`, `SubaccountAccessToken`, `PixAddressKey`, `PixAutomaticAuthorization`, `PixAutomaticPaymentInstruction` e `RecurringPixItem`, cada um com a respectiva `*List`.

## 7. Configuração

O service provider agora faz `mergeConfigFrom`. **Publicar `config/asaas.php` deixa de ser obrigatório**: sem o arquivo, `ASAAS_API_KEY`, `ASAAS_ENV` e as demais variáveis passam a valer. Na 1.x, sem o arquivo publicado, o SDK usava sandbox com API key vazia. Quem já publicou o arquivo não precisa fazer nada, mas leia a seção seguinte.

## 8. Retry: POST não é mais repetido

O padrão de `retry.methods` passou de `['GET', 'POST', 'PUT', 'PATCH', 'DELETE']` para `['GET', 'PUT', 'DELETE']`. Repetir um `POST` após erro 5xx ou timeout pode **duplicar cobranças ou transferências**, porque o SDK não envia chave de idempotência.

Se você já publicou `config/asaas.php` na 1.x, o seu arquivo **continua com `POST` na lista** e o novo padrão não se aplica a ele. Atualize `retry.methods` manualmente (ou remova a chave `retry` para usar os padrões do pacote). Só tem efeito quando `retry.max_retries` é maior que 1.

Para habilitar `POST` de propósito:

```php
// config/asaas.php
'retry' => [
    'max_retries' => 3,
    'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
],
```

> **Atenção:** habilite apenas se você deduplica no seu lado (por exemplo com `externalReference` único e consulta antes de recriar). Sem isso, um retry pode cobrar ou transferir duas vezes.

## 9. Mudanças internas que podem afetar subclasses ou mocks

- `AsaasHttpClient::send()` ganhou o parâmetro opcional `bool $multipart = false`.
- `RequestBuilder` ganhou `setQueryParameters()` e `setMultipartContent()`, e os parâmetros de rota passam por `rawurlencode`.
- `BaseService` mapeia HTTP 400 para `ErrorResponseDtoException` por padrão.
- Corpos JSON vazios são enviados como `{}` (antes, `[]`).
- Não há mais header global `Content-Type`: ele é definido por requisição.

## 10. Classes deletadas

Remova `use` e type-hints destas classes. Elas só guardavam resultados vazios ou 404.

Models (`Asaas\Laravel\Models`):

- `AccountInfoList`, `CheckoutList`, `CreditCardList`, `EscrowAccountList`, `FinanceList`, `FiscalInfoList`
- `Pix`, `PixList`
- `SandboxAction`, `SandboxActionList`

Os models `AccountInfo`, `Checkout`, `CreditCard`, `EscrowAccount`, `Finance` e `FiscalInfo` continuam existindo.

Classes `@internal` de request (`Asaas\Laravel\Http\Requests\...`), que não faziam parte da API pública: `AccountDocument\ListAccountDocumentsRequest`, `AccountInfo\ListAccountInfoRequest`, `Checkout\ListCheckoutRequest`, `CreditCard\ListCreditCardRequest`, `EscrowAccount\ListEscrowAccountsRequest`, `Finance\ListFinanceRequest`, `FiscalInfo\ListFiscalInfoRequest`, `Notification\ListNotificationsRequest`, `PaymentDocument\ListPaymentDocumentsRequest`, `PaymentRefund\ListPaymentRefundsRequest`, `PaymentSplit\ListPaymentSplitsRequest`, `Pix\ListPixRequest` e `SandboxAction\ListSandboxActionsRequest`.

As classes de request restantes foram regeneradas e perderam o construtor, `fromArray()`, `toArray()` e `toQuery()`; agora só declaram `rules()` e herdam `validate()` e `validateQuery()` de `AsaasRequest`. Como são `@internal`, não use diretamente.

## Novidades

- Cobertura de 213 de 213 endpoints, em 34 serviços (novos: `pixAutomatic` e `receivableRegistration`). Índice em [docs/README-services.md](docs/README-services.md).
- Código gerado a partir de `tools/endpoint-map.json`: `composer generate` e `composer generate:check`.
