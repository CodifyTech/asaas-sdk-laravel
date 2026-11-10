# Playbook de uso com IA

Receitas passo a passo para devs e agentes de IA (Claude Code, Cursor, Laravel Boost) que escrevem código de aplicação com este SDK. Todos os métodos abaixo foram conferidos em `src/Services/*.php` e as regras de validação em `src/Http/Requests/**`. Para a skill consumida pelos agentes, veja `resources/boost/skills/asaas-sdk-laravel/SKILL.md`.

## Regras que valem para todas as receitas

- Acesso: `asaas()->{propriedade}->{metodo}()` ou injete `Asaas\Laravel\AsaasSdk`. Os serviços individuais não estão no container.
- Assinatura: parâmetros de rota, depois `array $data` (corpo), depois `array $params` (query).
- Valores monetários são decimais em reais (`129.90`), nunca centavos. Datas são `Y-m-d`.
- POST não sofre retry por padrão. Não adicione `POST` em `retry.methods`: o SDK não envia chave de idempotência.
- Use `externalReference` como chave de idempotência da sua aplicação e consulte antes de recriar.
- Nunca registre em log a API key, número do cartão, `ccv` ou `creditCardToken`.
- Teste sempre em sandbox (`ASAAS_ENV=sandbox`) antes de produção.
- Os exemplos usam CPF e IDs fictícios. Em sandbox, a chave de API é diferente da de produção.

Configuração mínima:

```env
ASAAS_API_KEY=sua_chave_sandbox
ASAAS_ENV=sandbox
```

```bash
php artisan vendor:publish --tag=asaas-config
```

---

## 1. Cliente, cobrança Pix e QR Code

Passos: criar o cliente (uma vez por usuário), criar a cobrança `PIX`, buscar o QR Code.

```php
use Asaas\Laravel\Exceptions\ErrorResponseDtoException;

function chargeWithPix(User $user, Order $order): array
{
    // 1. Reaproveita o cliente pelo externalReference (idempotência)
    $existing = asaas()->customer->list(['externalReference' => (string) $user->id, 'limit' => 1]);

    $customer = $existing->items[0] ?? asaas()->customer->create([
        'name' => $user->name,
        'cpfCnpj' => $user->cpf,
        'email' => $user->email,
        'externalReference' => (string) $user->id,
    ]);

    // 2. Cobrança Pix (value em reais)
    $payment = asaas()->payment->create([
        'customer' => $customer->id,
        'billingType' => 'PIX',
        'value' => 129.90,
        'dueDate' => now()->addDay()->format('Y-m-d'),
        'description' => "Pedido #{$order->id}",
        'externalReference' => (string) $order->id,
    ]);

    // 3. QR Code: GenericResource com encodedImage (base64), payload (copia e cola), expirationDate
    $qr = asaas()->payment->getPixQrCode($payment->id);

    return [
        'payment_id' => $payment->id,
        'image' => 'data:image/png;base64,' . $qr->data['encodedImage'],
        'copy_paste' => $qr->data['payload'],
        'expires_at' => $qr->data['expirationDate'] ?? null,
    ];
}
```

Notas:

- `payment->create` devolve `Payment` (`id`, `customer`, `value`, `status` e `data` com o payload completo).
- A confirmação do pagamento vem pelo webhook `PAYMENT_RECEIVED` ou `PAYMENT_CONFIRMED` (receita 8), nunca pela resposta do `create`.
- Pix estático sem cobrança: `asaas()->pix->createStaticQrCode(['addressKey' => ..., 'value' => ..., 'externalReference' => ...])`.

---

## 2. Boleto, PDF e linha digitável

```php
$payment = asaas()->payment->create([
    'customer' => $customerId,
    'billingType' => 'BOLETO',
    'value' => 250.00,
    'dueDate' => '2026-12-15',
    'description' => 'Mensalidade dezembro',
    'externalReference' => 'invoice-2026-12-001',
]);

// URL do boleto em PDF/HTML e da fatura: campos do payload bruto
$bankSlipUrl = $payment->data['bankSlipUrl'] ?? null;
$invoiceUrl = $payment->data['invoiceUrl'] ?? null;

// Linha digitável: GenericResource com identificationField, nossoNumero, barCode
$field = asaas()->payment->getIdentificationField($payment->id);
$line = $field->data['identificationField'];
$barCode = $field->data['barCode'];
```

Notas:

- Não existe método que baixe o PDF de uma cobrança avulsa. Use `bankSlipUrl` (link) do payload.
- Para o carnê em PDF de uma assinatura ou parcelamento existem `asaas()->subscription->getPaymentBook($id, $params)` e `asaas()->installment->getPaymentBook($id, $params)`. Retornam `string` (bytes do PDF):

```php
use Illuminate\Support\Facades\Storage;

$pdf = asaas()->installment->getPaymentBook($installmentId);
Storage::put("carnes/{$installmentId}.pdf", $pdf);
```

---

## 3. Cartão de crédito e tokenização

Dois caminhos: cobrar com os dados do cartão, ou tokenizar uma vez e reutilizar o `creditCardToken`. Os dados do cartão devem vir do formulário do cliente e nunca ser persistidos ou logados. `remoteIp` (IP do comprador) é obrigatório.

Cobrança direta:

```php
$payment = asaas()->payment->createWithCreditCard([
    'customer' => $customerId,
    'billingType' => 'CREDIT_CARD',
    'value' => 99.90,
    'dueDate' => now()->format('Y-m-d'),
    'externalReference' => (string) $order->id,
    'creditCard' => [
        'holderName' => $request->input('card.holder'),
        'number' => $request->input('card.number'),
        'expiryMonth' => $request->input('card.month'),
        'expiryYear' => $request->input('card.year'),
        'ccv' => $request->input('card.ccv'),
    ],
    'creditCardHolderInfo' => [
        'name' => $user->name,
        'email' => $user->email,
        'cpfCnpj' => $user->cpf,
        'postalCode' => '01310100',
        'addressNumber' => '1000',
        'phone' => '1133334444',
    ],
    'remoteIp' => $request->ip(),
]);
```

Tokenizar e reutilizar:

```php
$card = asaas()->creditCard->tokenize([
    'customer' => $customerId,
    'creditCard' => [/* holderName, number, expiryMonth, expiryYear, ccv */],
    'creditCardHolderInfo' => [/* name, email, cpfCnpj, postalCode, addressNumber, phone */],
    'remoteIp' => $request->ip(),
]);

$token = $card->data['creditCardToken'];   // guarde (criptografado); ultimos 4 digitos em creditCardNumber

$payment = asaas()->payment->createWithCreditCard([
    'customer' => $customerId,
    'billingType' => 'CREDIT_CARD',
    'value' => 99.90,
    'dueDate' => now()->format('Y-m-d'),
    'creditCardToken' => $token,
    'remoteIp' => $request->ip(),
]);
```

Outras operações existentes: `payment->payWithCreditCard($id, $data)` (paga uma cobrança já criada), `payment->captureAuthorizedPayment($id)` (captura após `authorizeOnly => true`).

---

## 4. Assinatura e troca de cartão

```php
// Assinatura por boleto/Pix
$subscription = asaas()->subscription->create([
    'customer' => $customerId,
    'billingType' => 'PIX',
    'value' => 49.90,
    'nextDueDate' => '2026-12-10',
    'cycle' => 'MONTHLY',   // WEEKLY, BIWEEKLY, MONTHLY, BIMONTHLY, QUARTERLY, SEMIANNUALLY, YEARLY
    'description' => 'Plano Pro',
    'externalReference' => "sub-{$user->id}",
]);

// Assinatura com cartão (ou creditCardToken no lugar de creditCard + creditCardHolderInfo)
$subscription = asaas()->subscription->createWithCreditCard([
    'customer' => $customerId,
    'billingType' => 'CREDIT_CARD',
    'value' => 49.90,
    'nextDueDate' => '2026-12-10',
    'cycle' => 'MONTHLY',
    'creditCardToken' => $token,
    'remoteIp' => $request->ip(),
]);

// Trocar o cartão: aceita creditCard + creditCardHolderInfo OU creditCardToken
asaas()->subscription->updateCreditCard($subscription->id, [
    'creditCardToken' => $newToken,
    'remoteIp' => $request->ip(),
]);

// Atualizar, listar cobranças geradas, cancelar
asaas()->subscription->update($subscription->id, ['value' => 59.90, 'updatePendingPayments' => true]);
$payments = asaas()->subscription->listPayments($subscription->id, ['limit' => 20]);   // PaymentList
asaas()->subscription->delete($subscription->id);                                      // DeleteResult
```

---

## 5. Parcelamento

`asaas()->installment->create` (boleto/Pix) e `createWithCreditCard` (cartão). Atenção: em parcelamentos o split usa a chave `splits` (no pagamento é `split`).

```php
$installment = asaas()->installment->create([
    'customer' => $customerId,
    'billingType' => 'BOLETO',
    'installmentCount' => 6,
    'value' => 50.00,            // valor de CADA parcela (ou use 'totalValue' => 300.00)
    'dueDate' => '2026-12-10',
    'paymentExternalReference' => (string) $order->id,
]);

$payments = asaas()->installment->listPayments($installment->id);   // PaymentList

// Cartão em N vezes
$installment = asaas()->installment->createWithCreditCard([
    'customer' => $customerId,
    'billingType' => 'CREDIT_CARD',
    'installmentCount' => 3,
    'totalValue' => 300.00,
    'dueDate' => now()->format('Y-m-d'),
    'creditCardToken' => $token,
    'remoteIp' => $request->ip(),
]);
```

Outros: `installment->cancelPayments($id)` cancela as parcelas pendentes, `installment->updateSplits($id, $data)`, `installment->delete($id)`.

---

## 6. Split (wallets)

O split divide o valor da cobrança entre wallets (`walletId` de outras contas Asaas) e vai no corpo da cobrança. Use `fixedValue` (reais) ou `percentualValue` (porcentagem).

```php
$payment = asaas()->payment->create([
    'customer' => $customerId,
    'billingType' => 'PIX',
    'value' => 200.00,
    'dueDate' => now()->addDay()->format('Y-m-d'),
    'externalReference' => (string) $order->id,
    'split' => [
        ['walletId' => $sellerWalletId, 'percentualValue' => 80.0, 'externalReference' => "seller-{$order->id}"],
        ['walletId' => $partnerWalletId, 'fixedValue' => 10.00],
    ],
]);
```

Consulta dos repasses: `asaas()->paymentSplit->listPaid()`, `getPaid($id)`, `listReceived()`, `getReceived($id)` (retornam `PaymentSplitList` e `PaymentSplit`). O `walletId` de uma subconta vem na criação dela (receita 12). Assinaturas aceitam `split`; parcelamentos aceitam `splits`.

---

## 7. Transferência (Pix, TED e entre contas Asaas)

Estas chamadas movimentam dinheiro real. Em produção, exija confirmação humana e use `externalReference`. Em caso de timeout, consulte `transfer->list` antes de repetir.

```php
// Transferência por chave Pix
$transfer = asaas()->transfer->create([
    'value' => 100.00,
    'operationType' => 'PIX',
    'pixAddressKey' => 'fulano@example.com',
    'pixAddressKeyType' => 'EMAIL',   // CPF, CNPJ, EMAIL, PHONE, EVP
    'description' => 'Repasse pedido #123',
    'externalReference' => 'payout-123',
]);

// TED para conta bancária
$transfer = asaas()->transfer->create([
    'value' => 100.00,
    'operationType' => 'TED',
    'bankAccount' => [
        'bank' => ['code' => '237'],
        'ownerName' => 'Fulano de Tal',
        'cpfCnpj' => '12345678909',
        'agency' => '1234',
        'account' => '12345',
        'accountDigit' => '6',
        'bankAccountType' => 'CONTA_CORRENTE',
    ],
    'externalReference' => 'payout-124',
]);

// Entre contas Asaas
asaas()->transfer->createInternal(['value' => 50.00, 'walletId' => $walletId, 'externalReference' => 'internal-1']);

asaas()->transfer->get($transfer->id);      // acompanhar status
asaas()->transfer->cancel($transfer->id);   // apenas se ainda cancelável (agendada/pendente)
```

Para pagar um QR Code Pix de terceiro existem `asaas()->pixTransaction->decodeQrCode(['payload' => ...])` e `payQrCode(['qrCode' => ['payload' => ...], 'value' => ...])`. Decodifique e mostre o valor/favorecido ao usuário antes de pagar.

Obs.: o webhook `TRANSFER_DONE`/`TRANSFER_FAILED` é a fonte de verdade do resultado.

---

## 8. Receber webhooks

Passo 1: registrar o webhook (uma vez, por comando ou seeder). O `authToken` tem de 32 a 255 caracteres; guarde-o no `.env`.

```php
asaas()->webhook->create([
    'name' => 'Pagamentos',
    'url' => 'https://seuapp.com.br/webhooks/asaas',
    'email' => 'financeiro@seuapp.com.br',
    'enabled' => true,
    'interrupted' => false,
    'sendType' => 'SEQUENTIALLY',   // ou NON_SEQUENTIALLY
    'authToken' => config('services.asaas.webhook_token'),
    'events' => ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_OVERDUE', 'PAYMENT_REFUNDED'],
]);
```

Passo 2: migration para deduplicação por ID do evento.

```php
Schema::create('asaas_webhook_events', function (Blueprint $table) {
    $table->id();
    $table->string('event_id')->unique();   // evt_...
    $table->string('event');
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();
});
```

Passo 3: rota (fora do grupo `web`/CSRF) e controller. A Asaas envia o token configurado no header `asaas-access-token`.

```php
// routes/api.php
Route::post('/webhooks/asaas', AsaasWebhookController::class);
```

```php
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AsaasWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('services.asaas.webhook_token');
        $received = (string) $request->header('asaas-access-token', '');

        if ($expected === '' || ! hash_equals($expected, $received)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $eventId = (string) $request->input('id');
        $event = (string) $request->input('event');

        if ($eventId === '' || $event === '') {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        // Idempotência: insertOrIgnore retorna 0 se o event_id já existe (entrega duplicada)
        $inserted = DB::table('asaas_webhook_events')->insertOrIgnore([
            'event_id' => $eventId,
            'event' => $event,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 1) {
            ProcessAsaasEvent::dispatch($eventId, $request->all());   // job em fila
        }

        // Responder 200 rápido; a Asaas pausa a fila após falhas repetidas
        return response()->json(['received' => true]);
    }
}
```

Passo 4: o job lê `payment.id` e `payment.externalReference` do payload e atualiza o pedido de forma idempotente (por exemplo, só marcar como pago se ainda não estiver). Para não confiar apenas no payload, confirme com `asaas()->payment->get($paymentId)->status`.

Se a Asaas pausar a fila por falhas, corrija o endpoint e chame `asaas()->webhook->removeBackoff($webhookId)`; depois reative a fila no painel, se necessário. Em multi-conta, use um `authToken` por webhook/conta.

---

## 9. Estornos

Movimenta dinheiro real. Confirme valor e cobrança com o usuário.

```php
// Estorno total
$payment = asaas()->paymentRefund->refundPayment($paymentId, ['description' => 'Cancelamento pelo cliente']);

// Estorno parcial
asaas()->paymentRefund->refundPayment($paymentId, ['value' => 30.00, 'description' => 'Item devolvido']);

// Parcelamento inteiro e boleto
asaas()->paymentRefund->refundInstallment($installmentId);
asaas()->paymentRefund->refundBankSlip($paymentId);

// Estornos já feitos de uma cobrança
$refunds = asaas()->paymentRefund->list($paymentId);   // PaymentRefundList
```

`refundPayment` retorna `Payment`. Para cobranças com split, informe `splitRefunds` (`[['id' => ..., 'value' => ...]]`). O resultado final chega pelos webhooks `PAYMENT_REFUNDED`, `PAYMENT_PARTIALLY_REFUNDED` ou `PAYMENT_REFUND_DENIED`.

---

## 10. Erros e retry

```php
use Asaas\Laravel\Exceptions\ApiError;
use Asaas\Laravel\Exceptions\ErrorResponseDtoException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

try {
    $payment = asaas()->payment->create($data);
} catch (ValidationException $e) {
    // Entrada inválida; nada foi enviado
    return back()->withErrors($e->errors());
} catch (ErrorResponseDtoException $e) {
    // HTTP 400. Capture ANTES de ApiError (herda dela)
    $messages = collect($e->getErrors())->pluck('description')->all();   // textos em PT-BR
    return back()->withErrors(['asaas' => $messages]);
} catch (ApiError $e) {
    // 401 (chave inválida), 404, 429, 5xx...
    report($e);
    logger()->error('Asaas error', ['status' => $e->statusCode]);
    throw $e;
} catch (ConnectionException $e) {
    // Timeout/rede: a cobrança pode ter sido criada. Consulte antes de repetir:
    report($e);
    $existing = asaas()->payment->list(['externalReference' => $ref, 'limit' => 1]);
}
```

Regras de retry (`config/asaas.php`):

- `retry.max_retries` é o total de tentativas (padrão `1` = sem retry); a espera é fixa (`initial_delay` ms).
- `retry.methods` padrão: `['GET', 'PUT', 'DELETE']`. POST fica de fora de propósito: não o adicione.
- Para operações de leitura, aumentar `ASAAS_RETRY_MAX=3` é seguro. Para criar (POST), implemente retry na aplicação com `externalReference` + consulta prévia.
- Chaves de query não suportadas em `list()` lançam `ValidationException` (o SDK não deixa a Asaas ignorar o filtro).

---

## 11. Testes com Http::fake

```php
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

public function test_creates_pix_charge(): void
{
    config(['asaas.api_key' => 'test_key', 'asaas.environment' => 'sandbox']);   // antes do primeiro asaas()

    Http::fake([
        'api-sandbox.asaas.com/v3/payments' => Http::response(['id' => 'pay_1', 'customer' => 'cus_1', 'value' => 129.9, 'status' => 'PENDING'], 200),
        'api-sandbox.asaas.com/v3/payments/pay_1/pixQrCode' => Http::response(['encodedImage' => 'AAA', 'payload' => '000201...', 'expirationDate' => '2026-12-31 23:59:59'], 200),
    ]);

    $result = chargeWithPix($user, $order);

    $this->assertSame('pay_1', $result['payment_id']);
    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && $r->url() === 'https://api-sandbox.asaas.com/v3/payments'
        && $r['billingType'] === 'PIX'
        && $r['value'] === 129.9
        && $r->hasHeader('access_token', 'test_key'));
}

public function test_maps_validation_error(): void
{
    config(['asaas.api_key' => 'test_key']);
    Http::fake(['*' => Http::response(['errors' => [['code' => 'invalid_object', 'description' => 'CPF inválido']]], 400)]);

    $this->expectException(\Asaas\Laravel\Exceptions\ErrorResponseDtoException::class);
    asaas()->customer->create(['name' => 'Fulano', 'cpfCnpj' => '000']);
}
```

Dicas: o SDK é singleton; defina a config antes de resolvê-lo. Nunca bata na API real nos testes automatizados. Para testar o webhook, envie `postJson('/webhooks/asaas', $payload, ['asaas-access-token' => 'token'])` duas vezes e verifique que o job é despachado uma única vez.

Em testes manuais no sandbox, simule eventos com o serviço de ações (só existe em sandbox):

```php
asaas()->sandboxActions->confirmPayment($paymentId);   // simula o pagamento (dispara PAYMENT_RECEIVED/CONFIRMED)
asaas()->sandboxActions->forceOverdue($paymentId);     // força PAYMENT_OVERDUE
asaas()->sandboxActions->approveAccount();             // aprova a conta sandbox
```

---

## 12. Multi-conta e subcontas

Crie a subconta com a conta raiz. A chave de API da subconta é devolvida uma única vez na criação, em `accessToken.apiKey`, junto com o `walletId` (usado em split e transferência interna).

```php
$sub = asaas()->subaccount->create([
    'name' => 'Loja do João',
    'email' => 'joao@loja.com.br',
    'cpfCnpj' => '12345678909',
    'mobilePhone' => '11999999999',
    'incomeValue' => 5000.00,
    'address' => 'Av. Paulista',
    'addressNumber' => '1000',
    'province' => 'Bela Vista',
    'postalCode' => '01310100',
]);

$tenant->update([
    'asaas_account_id' => $sub->id,
    'asaas_wallet_id' => $sub->data['walletId'] ?? null,
    'asaas_api_key' => encrypt($sub->data['accessToken']['apiKey']),   // não perca: não é possível ler de novo
]);
```

Operar como a subconta, com instância independente do singleton:

```php
use Asaas\Laravel\AsaasSdk;

function asaasFor(Tenant $tenant): AsaasSdk
{
    return AsaasSdk::fromConfig(array_merge(config('asaas'), [
        'api_key' => decrypt($tenant->asaas_api_key),
    ]));
}

$payment = asaasFor($tenant)->payment->create([/* ... */]);
```

Outros: `subaccount->list()`, `get($id)`, `listAccessTokens($id)`, `createAccessToken($id, ['name' => ...])` (nova chave), `deleteAccessToken($id, $accessTokenId)`, `resendActivationLink($id)`. Cada subconta pode ter seu próprio webhook, com `authToken` próprio.

---

## Lacunas conhecidas

- Não existe método para baixar o PDF de uma cobrança avulsa; use `bankSlipUrl` do payload (receita 2).
- Não há verificação de assinatura HMAC no webhook: o mecanismo oficial é o token no header `asaas-access-token`. O SDK não fornece helper para receber webhooks; implemente o controller como na receita 8.
- O SDK não envia chave de idempotência; use `externalReference` + consulta.

---

## Prompts prontos

Copie e cole para o agente. Eles assumem que a skill `asaas-sdk-laravel` está instalada ou que o agente leu este playbook.

- "Usando o asaas-sdk-laravel, crie um service `PixCheckoutService` que cria (ou reaproveita por externalReference) o cliente, gera uma cobrança Pix e retorna a imagem do QR Code e o copia e cola. Valores em reais, teste com Http::fake."
- "Implemente o recebimento de webhooks da Asaas: rota, controller que valida o header asaas-access-token com hash_equals, tabela de deduplicação por id do evento e job em fila que marca o pedido como pago em PAYMENT_RECEIVED."
- "Adicione cobrança por cartão com tokenização usando asaas()->creditCard->tokenize e creditCardToken. Não logue dados do cartão e use request()->ip() como remoteIp."
- "Crie assinatura mensal por cartão e um endpoint para trocar o cartão com subscription->updateCreditCard."
- "Gere cobrança em 6 parcelas no boleto com installment->create e liste as parcelas."
- "Configure split de 80% para o wallet do vendedor na cobrança do pedido e exiba os repasses com paymentSplit."
- "Escreva testes Pest com Http::fake para PaymentService: sucesso, erro 400 (ErrorResponseDtoException) e erro 500 (ApiError). Não use a API real."
- "Crie o fluxo de onboarding de subconta por tenant, guarde a apiKey criptografada e crie um helper asaasFor($tenant) com AsaasSdk::fromConfig."
- "Revise meu código que usa o asaas-sdk-laravel: valores em centavos, retry em POST, falta de externalReference, logs com chave de API, capturas de ApiError antes de ErrorResponseDtoException."
- "Antes de fazer qualquer transferência ou estorno, mostre o código e peça minha confirmação; use apenas sandbox."
