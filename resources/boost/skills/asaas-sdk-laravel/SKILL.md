---
name: asaas-sdk-laravel
description: Use when integrating Asaas payments in a Laravel app with codifytech/asaas-sdk-laravel (Asaas API v3): charges (cobrancas), Pix, boleto, credit card and tokenization, subscriptions, installments, split, refunds, transfers, webhooks, subaccounts, sandbox testing. Triggers on `asaas()`, `Asaas\Laravel\AsaasSdk`, ASAAS_API_KEY, "Asaas".
---

# asaas-sdk-laravel

PHP SDK for Asaas API v3 on top of Laravel's `Http` facade, by CodifyTech. 213 endpoints in 34 services, each one a readonly property of `Asaas\Laravel\AsaasSdk`. Requires PHP ^8.4 and Laravel 11, 12 or 13. It only works inside a booted Laravel app (needs the `Http` and `Validator` facades).

The code is the source of truth: before using a method, confirm its name and signature in `src/Services/{Name}Service.php` and its rules in `src/Http/Requests/{Service}/`. Never invent a method; if it is not there, say so.

## Setup

1. `composer require codifytech/asaas-sdk-laravel` (provider and `asaas()` helper are auto-discovered).
2. Optional: `php artisan vendor:publish --tag=asaas-config` (creates `config/asaas.php`; only needed to edit keys without an env var, such as `retry.methods`).
3. `.env`:

```env
ASAAS_API_KEY=your_key
ASAAS_ENV=sandbox          # production | prod | sandbox (case-insensitive); any other value throws InvalidArgumentException
ASAAS_TIMEOUT=10000        # milliseconds
ASAAS_RETRY_MAX=1          # retries after the first attempt (0 to 10), 0 = no retry
# ASAAS_BASE_URL=          # overrides the environment URL; must be https:// (http:// only for localhost/127.0.0.1/[::1]); leave unset otherwise
```

Keys in `config/asaas.php`: `api_key`, `environment`, `base_url`, `timeout`, `user_agent` (default `codifytech-asaas-sdk-laravel/1.0.0`), `retry.{max_retries,initial_delay,max_delay,backoff_factor,jitter,status_codes,methods}`. The key is sent in the `access_token` header. Real URLs: `https://api-sandbox.asaas.com` and `https://api.asaas.com`.

## Access patterns

```php
use Asaas\Laravel\AsaasSdk;

asaas()->payment->get('pay_123');            // helper (singleton)

public function __construct(private readonly AsaasSdk $asaas) {}   // DI
$this->asaas->customer->list(['limit' => 10]);

// Multi-account (subaccount key, tenant key): independent instance, same config keys
$tenant = AsaasSdk::fromConfig(array_merge(config('asaas'), ['api_key' => $tenantApiKey]));
```

Individual services (`PaymentService`...) are NOT in the container; `app(PaymentService::class)` fails. Always go through the `AsaasSdk` property. Resolve the singleton after setting config (in tests, call `config([...])` first).

## Method conventions

- Verbs: `list`, `get`, `create`, `update`, `delete`, plus API verbs (`cancel`, `restore`, `refundPayment`, `tokenize`, `confirmPayment`...).
- Argument order: path params (strings), then `array $data` (body), then `array $params` (query). Example: `asaas()->subscription->updateCreditCard($id, $data)`, `asaas()->subscription->listPayments($id, ['limit' => 20])`, `asaas()->paymentDocument->update($id, $documentId, $data)`.
- Bodies and queries are plain arrays with the exact camelCase keys of the Asaas API (`billingType`, `dueDate`, `externalReference`).
- Dates are `Y-m-d` strings (`'2026-12-15'`). Money is a decimal number in BRL (`129.90` = R$ 129,90), NOT cents.

## Filters, validation, pagination

- Filter keys with brackets are literal array keys: `['paymentDate[ge]' => '2026-01-01', 'dueDate[le]' => '2026-01-31']`.
- Query validation is strict: an unsupported key throws `Illuminate\Validation\ValidationException` (Asaas would silently ignore it and return an unfiltered list). Enum values (`status`, `billingType`) are validated too.
- Body validation covers required fields and enums, but extra keys pass through unchanged, so fields newer than the SDK still reach the API.
- Validation runs before any HTTP call; on failure nothing is sent.
- Pagination: `offset` (>= 0) and `limit` (1 to 100). List models expose `items`, `totalCount`, `hasMore`:

```php
$offset = 0;
do {
    $page = asaas()->payment->list(['status' => 'PENDING', 'offset' => $offset, 'limit' => 100]);
    foreach ($page->items as $payment) { /* ... */ }
    $offset += 100;
} while ($page->hasMore === true);
```

## File inputs

Upload methods (`accountDocument->upload`, `paymentDocument->create`, `paymentLink->addImage`...) take `\SplFileInfo` (including `Illuminate\Http\UploadedFile`) or a stream resource, or a list of them. NEVER pass a string path. Use `new \SplFileInfo($path)` or `$request->file('document')`. Multipart is POST only.

## Return types

| Return | When |
|--------|------|
| Typed model (`Payment`, `Customer`, `Subscription`...) | Main resources. Has `id`, `data` (full raw payload) and a few typed fields (`Payment::$value` is `?float`, `$status`, `$customer`). Read other fields with `$model->data['bankSlipUrl']`. |
| `XxxList` (`PaymentList`...) | `list*` methods: `items`, `totalCount`, `hasMore`. |
| `GenericResource` | Free-form payloads (Pix QR code, identification field, limits, status). `->id`, `->data['encodedImage']`. |
| `GenericList` | Code tables and histories: `->items` (arrays), `totalCount`, `hasMore`. |
| `DeleteResult` / `PaymentDeleteResult` / `CustomerDeleteResult` | Deletions: `->deleted`. |
| `string` | PDF bodies (`subscription->getPaymentBook`, `installment->getPaymentBook`): raw bytes, store with `Storage::put()`. |
| `void` | HTTP 204 endpoints (`webhook->removeBackoff`, `subaccount->deleteAccessToken`, `subaccount->resendActivationLink`). |

All models implement `toArray()` (raw payload shape of the API).

## Errors

```php
use Asaas\Laravel\Exceptions\ApiError;
use Asaas\Laravel\Exceptions\ErrorResponseDtoException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

try {
    $payment = asaas()->payment->create($data);
} catch (ValidationException $e) {            // bad input, nothing was sent
    return back()->withErrors($e->errors());
} catch (ErrorResponseDtoException $e) {      // HTTP 400, catch BEFORE ApiError (it extends ApiError)
    $messages = collect($e->getErrors())->pluck('description')->all();   // PT-BR texts
    return back()->withErrors(['asaas' => $messages]);
} catch (ApiError $e) {                       // any other non-2xx
    report($e);
    logger()->error('Asaas error', ['status' => $e->statusCode]);
    throw $e;
} catch (ConnectionException $e) {            // timeout / network; the charge may or may not exist
    report($e);
    // Never blindly retry a POST: look it up first.
    $existing = asaas()->payment->list(['externalReference' => $ref, 'limit' => 1]);
}
```

Exception messages come from `errors[0].description`. Show `getErrors()` descriptions to the user (they are in PT-BR); never expose raw bodies that may contain customer data.

## Money-safety rules (non-negotiable)

1. POST is NOT retried by default (`retry.methods` is `['GET', 'PUT', 'DELETE']`). Do not add `POST` to `retry.methods`: the SDK sends no idempotency key and a retried POST can create duplicate charges or transfers.
2. Idempotency is your job: send `externalReference` (unique per order/invoice in your app) on customers, charges, subscriptions and transfers, and before re-creating after a timeout or `ConnectionException`, look it up with `list(['externalReference' => $ref])`.
3. Never log, dump, return or commit the API key, or card data (`number`, `ccv`) and `creditCardToken`. Do not log full request bodies.
4. Values are decimal BRL, never cents. `value => 10000` is R$ 10.000,00. Convert cents with `round($cents / 100, 2)`.
5. Always test in sandbox first (`ASAAS_ENV=sandbox`). Switching to production is a deliberate, separate step with a different API key. Never run transfers, refunds or payments of QR codes/bills against production from an agent without explicit human approval.
6. `transfer->create`, `pixTransaction->payQrCode`, `bill->create`, `paymentRefund->refundPayment` move real money: confirm amount and destination with the user before generating code that calls them with real values.
7. Confirm payment by webhook (`PAYMENT_RECEIVED` / `PAYMENT_CONFIRMED`) or by `get()`, never by the create response or a redirect.

## Webhooks

Asaas sends the token you set in `authToken` (32 to 255 chars) in the `asaas-access-token` request header. Compare it with `hash_equals`. The body has an event `id` (`evt_...`), `event` (`PAYMENT_RECEIVED`...) and the resource (`payment`...). Deduplicate by event `id` (delivery is at-least-once), answer 200 fast and process in a queued job. Exclude the route from CSRF. Full recipe: `docs/ai-playbook.md`. Register hooks with `asaas()->webhook->create([...])` (required: `name`, `url`, `email`, `interrupted`, `enabled`, `events`, `sendType`).

## Testing

```php
use Illuminate\Support\Facades\Http;

config(['asaas.api_key' => 'test_key', 'asaas.environment' => 'sandbox']);   // before asaas() is first resolved
Http::fake(['api-sandbox.asaas.com/*' => Http::response(['id' => 'pay_1', 'status' => 'PENDING', 'value' => 100.0], 200)]);

$payment = asaas()->payment->create([...]);
Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->url() === 'https://api-sandbox.asaas.com/v3/payments');
```

Use `Http::fake` with `Http::response(['errors' => [['code' => 'invalid_object', 'description' => 'x']]], 400)` to test the error path. Do not hit the real API in the test suite.

Sandbox only: `asaas()->sandboxActions->confirmPayment($paymentId)` simulates payment (Pix/boleto/card), `forceOverdue($paymentId)` makes it overdue, `approveAccount()` approves a sandbox account. These do not exist in production.

## Gotchas

- `ASAAS_ENV` typos (`producao`) throw `InvalidArgumentException` when the SDK is built. A `base_url` with `http://` (non-loopback host) throws too.
- Path params (ids) that are empty, `.` or `..` throw `InvalidArgumentException` before any request; other values are URL-encoded. Check ids from user input before calling.
- `retry.max_retries` is the number of retries after the first attempt (0 = none, default 1, max 10), only for `retry.methods`. Wait before retry n is `min(initial_delay * backoff_factor^(n-1), max_delay)` ms plus random jitter of 0 to `jitter` ms (defaults 150, 2, 1000, 150); a numeric `Retry-After` is honored up to `max_delay`. `timeout` is milliseconds and applies per attempt.
- The API key is `#[\SensitiveParameter]` and shown as `***` in `var_dump`/`print_r` of the config objects; your own logs are still your responsibility.
- Network failures throw `Illuminate\Http\Client\ConnectionException`, not `ApiError`.
- Split key differs: `split` on payments and subscriptions, `splits` on installments (`installmentNumber` allowed there).
- `value` plus `installmentCount` on installments: `value` is per installment, or send `totalValue`.
- Credit card creation needs `remoteIp` (the end customer's IP, `request()->ip()`), and either `creditCard` + `creditCardHolderInfo` or `creditCardToken`. Use `payment->createWithCreditCard`, `subscription->createWithCreditCard`, `installment->createWithCreditCard` to charge a card directly (these validate the card fields; plain `create` does not).
- `creditCard->tokenize` needs `customer`, `creditCard`, `creditCardHolderInfo`, `remoteIp`. The token is only reusable for that customer.
- There is no single-charge PDF method: read `bankSlipUrl` / `invoiceUrl` from `$payment->data`. Boleto line: `payment->getIdentificationField($id)` (`identificationField`, `barCode`, `nossoNumero`). Pix: `payment->getPixQrCode($id)` (`encodedImage` base64, `payload` copy-and-paste, `expirationDate`).
- Subaccount `create` returns the new key once, in `$sub->data['accessToken']['apiKey']`, plus `walletId`. Store it encrypted immediately; it cannot be read again.
- A webhook queue is paused after repeated failures; `webhook->removeBackoff($id)` clears the penalty, and the queue must be re-enabled in Asaas.
- Strict `list()` query keys: an unknown filter throws instead of being ignored; check the `rules()` of `src/Http/Requests/{Service}/List*Request.php`.

## References

Per-service references (methods, signatures, return types, examples): `references/{property}.md`, where `{property}` is the `asaas()` property.

| Property | Purpose |
|----------|---------|
| payment | Charges: create, list, PIX QR code, boleto line, card payment, cash receipt |
| paymentWithSummaryData | Charges returning summary data (lean variants) |
| sandboxActions | Sandbox only: confirm payment, force overdue, approve account |
| creditCard | Card tokenization and pre-authorization config |
| paymentRefund | Refunds of payments, installments and boleto |
| paymentSplit | Read paid and received split records |
| escrowAccount | Escrow configuration and payment escrow |
| paymentDocument | Documents attached to a charge |
| customer | Customers: CRUD, restore, notifications |
| notification | Update customer notification settings |
| installment | Installments: create, payments, carnet PDF, splits |
| subscription | Recurring charges, card update, invoice settings |
| pix | Pix address keys and static QR codes |
| pixTransaction | Pix transactions, pay or decode a QR code |
| anticipation | Receivable anticipation |
| recurringPix | Recurring Pix authorizations and items |
| paymentLink | Payment links and images |
| checkout | Hosted checkout sessions |
| transfer | Transfers to bank accounts, Pix keys, internal wallets |
| paymentDunning | Dunning (negativacao) |
| bill | Pay bills (boletos de terceiros) |
| mobilePhoneRecharge | Mobile phone top-ups |
| creditBureauReport | Credit bureau queries |
| financialTransaction | Account statement |
| finance | Balance and payment/split statistics |
| accountInfo | Own account info, fees, wallets, commercial info |
| invoice | Fiscal invoices (NFS-e) |
| fiscalInfo | Fiscal configuration and municipal codes |
| webhook | Webhook configurations |
| subaccount | Subaccounts and their access tokens |
| accountDocument | Onboarding documents of subaccounts |
| chargeback | Chargebacks and disputes |
| pixAutomatic | Pix Automatico authorizations and instructions |
| receivableRegistration | Receivable registration restrictions |

Human docs: `docs/services/{Name}Service.md` (for example `docs/services/PaymentService.md`, `docs/services/BaseService.md`). Task recipes with working code: `docs/ai-playbook.md`. API field semantics: `tools/asaas-openapi.json` and https://docs.asaas.com.
