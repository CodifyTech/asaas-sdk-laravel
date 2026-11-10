# Full coverage plan: Asaas API v3 (26/213 -> 213/213)

Source of truth: `plans/endpoint-map.json` (one entry per official operation, built and validated by
`build_endpoint_map.py` from the merged official spec + endpoints.tsv). This document explains the rules
behind the map. Wave A2 copies the map to `tools/endpoint-map.json`, which becomes canonical.

Validation result: 213 entries, 213 unique (method, path), 34 categories -> 34 services, no duplicate
sdkMethod per service, 40 existing public methods = 30 kept + 10 removed (4 with replacement).
Status: existing-ok 7, existing-fix 24, new 182. Multipart 10, PDF 2, no-content (204) 3,
request classes 108 (5 shared), operations without request class 100.

## 1. Service mapping (one official category = one service = one AsaasSdk property)

No exceptions: cross-resource paths follow the official category (POST /v3/installments/{id}/refund is in
paymentRefund because the docs list it under Estornos; GET /v3/customers/{id}/notifications is in customer).
docs/services/*.md then mirror the official navigation and the generator needs no special cases.

| Category (official) | Property | Service | Ops |
|---|---|---|---|
| Clientes | customer | CustomerService | 7 |
| Cobranças | payment | PaymentService | 19 |
| Cobranças com dados resumidos | paymentWithSummaryData | PaymentWithSummaryDataService | 10 |
| Documentos de cobranças | paymentDocument | PaymentDocumentService | 5 |
| Parcelamentos | installment | InstallmentService | 9 |
| Assinaturas | subscription | SubscriptionService | 14 |
| Link de pagamentos | paymentLink | PaymentLinkService | 11 |
| Checkout | checkout | CheckoutService | 2 |
| Cartão de crédito | creditCard | CreditCardService | 3 |
| Antecipações | anticipation | AnticipationService | 8 |
| Estornos | paymentRefund | PaymentRefundService | 5 |
| Chargeback | chargeback | ChargebackService | 3 |
| Splits | paymentSplit | PaymentSplitService | 4 |
| Conta Escrow | escrowAccount | EscrowAccountService | 6 |
| Notificações | notification | NotificationService | 2 |
| Consulta Serasa | creditBureauReport | CreditBureauReportService | 3 |
| Negativações | paymentDunning | PaymentDunningService | 9 |
| Notas fiscais | invoice | InvoiceService | 6 |
| Informações fiscais | fiscalInfo | FiscalInfoService | 10 |
| Pix | pix | PixService | 8 |
| Transações Pix | pixTransaction | PixTransactionService | 5 |
| Pix Automático | pixAutomatic | PixAutomaticService (NEW) | 7 |
| Pix Recorrente | recurringPix | RecurringPixService | 5 |
| Transferências | transfer | TransferService | 5 |
| Pagamento de contas | bill | BillService | 5 |
| Recargas de celular | mobilePhoneRecharge | MobilePhoneRechargeService | 5 |
| Extrato | financialTransaction | FinancialTransactionService | 1 |
| Informações financeiras | finance | FinanceService | 3 |
| Informações e personalização da conta | accountInfo | AccountInfoService | 9 |
| Webhooks | webhook | WebhookService | 6 |
| Subcontas Asaas | subaccount | SubaccountService | 9 |
| Envio de documentos para contas BaaS | accountDocument | AccountDocumentService | 5 |
| Ações em sandbox | sandboxActions | SandboxActionsService | 3 |
| Registro de Recebíveis | receivableRegistration | ReceivableRegistrationService (NEW) | 1 |

All 32 existing property names are kept. ReceivableRegistrationService has one method; it exists to keep the
1:1 rule exception-free (cost: one small generated class).

## 2. Method naming and signatures

- CRUD on the service's primary resource: `list`, `get`, `create`, `update`, `delete`.
- Secondary resources: verb + noun (`listImages`, `getAddressKey`, `createAccessToken`, `deleteInvoiceSettings`,
  `listPaid`/`listReceived`).
- Actions use the API's own verb: `cancel`, `restore`, `refundPayment`, `refundInstallment`, `refundBankSlip`,
  `receiveInCash`, `undoReceivedInCash`, `captureAuthorizedPayment`, `payWithCreditCard`, `payWithCard`,
  `simulate`, `authorize`, `tokenize`, `upload`, `resendDocuments`, `removeBackoff`, `resendActivationLink`,
  `retryPaymentInstruction`, `setMainImage`, `addImage`, `confirmPayment`, `forceOverdue`, `approveAccount`.
- Upserts (POST that creates or updates a config): `save*` (`save`, `saveDefaultConfig`, `savePaymentCheckoutConfig`).
- Trailing-slash doc variants of a create (same endpoint, different body): `createWithCreditCard`
  (payments, lean payments, installments, subscriptions) and `createInternal` (transfers). Paths are sent
  verbatim, trailing slash included, exactly like the official Java SDK.
- Signature order: one `string $<name>` per path param (spec names, path order: `$id`, `$documentId`,
  `$paymentLinkId`, `$imageId`, `$accessTokenId`, `$phoneNumber`); then `array $data` when hasBody
  (required when the body schema has required fields, 45 of 68 bodies; otherwise `array $data = []`);
  then `array $params = []` when hasQuery or the method name starts with `list`. No operation has both body and query.
- Return type = responseModel; `string` for PDF; `void` for 204.
- Request classes: `src/Http/Requests/<Dir>/<Name>.php`. `create` -> `Store<Dir>Request`, `update` ->
  `Update<Dir>Request`, `list` -> the existing `List...Request` name when there is one, otherwise
  `Studly(sdkMethod)Request`. Identical body schemas share one class: `Payment\StorePaymentRequest`,
  `Payment\UpdatePaymentRequest`, `Payment\CreateWithCreditCardRequest`, `Payment\ReceiveInCashRequest`,
  `PaymentRefund\RefundPaymentRequest` are used by both the payment and the lean variant.
- Full per-operation assignment: endpoint-map.json (fields: method, path, category, operationId, service,
  property, sdkMethod, pathParams, hasQuery, hasBody, multipart, requestClass, responseModel, status, notes).

## 3. Backward compatibility and version

Recommended version: 2.0.0. Public methods are removed or change signature, model classes are deleted, and
validation and query behavior change in ways callers will notice. Widening to Laravel 9-13 / PHP 8.1-8.5 alone
would only need a minor; if that effort ships first as 1.1.0, this one is 2.0.0.

| Change | Methods |
|---|---|
| Removed (no listing in the API) | accountInfo.list, checkout.list, creditCard.list, escrowAccount.list, finance.list, sandboxActions.list |
| Removed with replacement | notification.list -> customer.listNotifications($id); pix.list -> pix.listAddressKeys(); paymentSplit.list -> paymentSplit.listPaid()/listReceived(); fiscalInfo.list -> fiscalInfo.get() (the endpoint returns one object; list() always produced an empty FiscalInfoList) |
| Signature change (only these two) | paymentDocument.list(string $id, array $params = []); paymentRefund.list(string $id, array $params = []) |
| Repointed, same signature | bill.list (/v3/bill), mobilePhoneRecharge.list (/v3/mobilePhoneRecharges), paymentWithSummaryData.list (/v3/lean/payments), recurringPix.list (/v3/pix/transactions/recurrings) |
| Same signature, validation now matches the spec | customer.create/update, payment.create/list/update, and every list whose filters were silently dropped (only offset/limit were validated): subscription, paymentLink, anticipation, chargeback, creditBureauReport, paymentDunning, invoice, pixTransaction, transfer, financialTransaction, subaccount, accountDocument |
| Unchanged (existing-ok) | customer.list/get/delete, payment.get/delete, installment.list, webhook.list |
| Deleted models (only ever held 404/empty results) | AccountInfoList, CheckoutList, CreditCardList, EscrowAccountList, FinanceList, FiscalInfoList, Pix, PixList, SandboxAction, SandboxActionList |
| Deleted @internal request classes | AccountDocument/ListAccountDocumentsRequest, AccountInfo/ListAccountInfoRequest, Checkout/ListCheckoutRequest, CreditCard/ListCreditCardRequest, EscrowAccount/ListEscrowAccountsRequest, Finance/ListFinanceRequest, FiscalInfo/ListFiscalInfoRequest, Notification/ListNotificationsRequest, PaymentDocument/ListPaymentDocumentsRequest, PaymentRefund/ListPaymentRefundsRequest, PaymentSplit/ListPaymentSplitsRequest, Pix/ListPixRequest, SandboxAction/ListSandboxActionsRequest |
| Additive only | AsaasHttpClient::send() optional `bool $multipart = false`; new RequestBuilder methods; BaseService default 400 mapping; AccountDocumentList `rejectReasons` |

Kept request classes are regenerated (23) and lose their unused constructor/fromArray/toArray/toQuery (they
are @internal). UPGRADE.md must list: removed methods + replacements, the two signature changes, bracket
filters (`paymentDate[ge]`, not `paymentDateGe`), object-shaped discount/interest/fine, strict query keys,
file inputs (`\SplFileInfo` or a stream, not path strings).

## 4. Response models

Applied in order:
1. 204 -> `void` (webhook.removeBackoff, subaccount.deleteAccessToken, subaccount.resendActivationLink).
   PDF -> `string` raw body (installment.getPaymentBook, subscription.getPaymentBook). These are the only
   non-JSON responses in the spec.
2. Same resource as an existing model -> reuse it (Payment for capture/restore/refund/cash/sandbox/escrow-finish;
   PaymentWithSummary; Subscription for createWithCreditCard/updateCreditCard; Transfer for createInternal;
   PaymentDunning for show/cancel/resendDocuments; PaymentSplit for paid and received; Chargeback;
   AccountInfo for commercialInfo; FiscalInfo; CreditCard for tokenize; Checkout; EscrowAccount for escrow
   configs; Finance for balance/payment statistics/split statistics; NotificationList for customer notifications;
   PaymentList/InvoiceList for sub-lists). Existing CustomerDeleteResult/PaymentDeleteResult stay (also lean delete).
3. New first-class resources (own get or list endpoint, has an id) -> named model + list, same
   shape as today (`id` + raw `data`): PaymentLinkImage(+List), SubaccountAccessToken(+List, list key
   `accessTokens`), PixAddressKey(+List), PixAutomaticAuthorization(+List), PixAutomaticPaymentInstruction(+List),
   RecurringPixItem(+List). 12 generated classes.
4. `{deleted, id}` shapes -> `DeleteResult` (`?bool $deleted`, `?string $id`, `array $data`).
5. List shapes (array `data`) of one-off items -> `GenericList` (fiscal code tables, dunning history/partial
   payments/available payments, wallets, contractual effect restrictions).
6. Everything else (status, QR code, limits, simulations, billing/viewing info, configs, token bucket,
   account number, fees) -> `GenericResource` (`?string $id`, `array $data`).
Rule check in the map script: the same response schema always maps to the same model (0 conflicts).
Hand-written in A1: GenericResource, GenericList, DeleteResult. No per-shape classes (avoids ~70 one-off classes
that would only add a name; every existing model is already `id` + `data`).

## 5. Request validation

- One generated class per unique input (body schema or query set), `final class X extends AsaasRequest`
  containing only `rules()`. Operations without body or query have no class.
- `AsaasRequest` (new, hand-written, A1):
  - `abstract public static function rules(): array;`
  - `validate(array $data): array` for bodies: runs the rules and returns the input unchanged, so fields
    newer than the SDK still reach the API.
  - `validateQuery(array $params): array` for queries: runs the rules and also rejects keys that have no
    rule. Asaas ignores unknown filters and returns an unfiltered list, which is a data-exposure risk.
  - `protected static function file(): \Closure` returns a closure rule that accepts an `\SplFileInfo` or a
    stream resource, or a list of them (closure rules work in Laravel 9-13).
- Mapping (generator RulesBuilder):

| OpenAPI | Rule |
|---|---|
| required, top level | `required` |
| required inside object `a` | `required_with:a` |
| required inside array items `a.*` | `required` (fires per item only) |
| not required | `nullable` |
| string / integer (int32, int64) / number / boolean | `string` / `integer` / `numeric` / `boolean` |
| string format date / date-time / binary | `date_format:Y-m-d` / `date` / `self::file()` |
| object | `array`, children as `a.b` (max depth 3, deeper is `array` only) |
| array | `array`, items as `a.*` or `a.*.b` |
| enum (also `$ref` enums in query) | `in:A,B` (generator aborts if a value contains a comma; none today) |
| maxLength/minLength, minimum/maximum | `max:N`/`min:N` |
| query names | verbatim, including bracket filters `paymentDate[ge]` (verified against the vendor Validator) |

- Spec bugs handled in the map `notes`: PUT /v3/lean/payments/{id} has an empty body in the official spec,
  so it reuses `Payment\UpdatePaymentRequest`. GET /v3/invoices lists `effectiveDate[Ge]/[Le]` with capitals,
  and the spec keys are kept as-is.

## 6. HTTP layer (Wave A1 contract; generated code depends on exactly this)

RequestBuilder:
- `setPathParameter()` stores `rawurlencode((string) $value)`.
- New `setQueryParameters(array $params): self` (skips nulls).
- New `setMultipartContent(array $fields): self`.
- `buildUrl()` turns booleans into `'true'`/`'false'` (today `true` becomes `1`).
- `build()` adds `'multipart' => bool`.

AsaasHttpClient::send(string $method, string $path, array $headers = [], mixed $body = null, bool $multipart = false):
- `$headers += ['Accept' => 'application/json']` so the caller's Accept wins. PDF operations pass
  `'application/pdf, application/json'` so errors still come back as JSON.
- No global Content-Type header.
- JSON (POST/PUT/PATCH): `withBody(null or [] ? '{}' : json_encode(...), 'application/json')`. Today a
  null body is sent as `[]`. The 27 empty-schema action bodies are sent as `{}`, like the Java SDK does.
- Multipart: `asMultipart()->post($path, $parts)`, where `$parts` is a list of
  `['name', 'contents', 'filename']` arrays, already in the format every Laravel version 9-13 passes through
  unchanged. Do not use `attach()`, which behaves differently across versions.
  - Booleans become `'true'`/`'false'` and other scalars are cast to string; nulls are skipped.
  - An array value becomes one part per item under the same name (multiple files).
  - Files: `\SplFileInfo` (Illuminate UploadedFile, Symfony File, plain SplFileInfo) or a stream resource,
    read into a string. Laravel parses the request options once before its retry loop, so a stream would
    already be consumed on a retry.
    `// shortcut: whole file in memory; Asaas upload limits are small, stream if that changes.`
  - Filename: `getClientOriginalName()` for uploads, otherwise `getFilename()` or the basename of the stream URI.
  - Plain strings are never treated as paths, so a request field like `"file": "/etc/passwd"` can't upload
    a local file. Callers wrap paths themselves: `new \SplFileInfo($path)`.

BaseService:
- `execute()` passes `$request['multipart'] ?? false` through to the client.
- `$errorMappings` defaults to `[400 => ErrorResponseDtoException]`, so generated methods don't repeat
  `addErrorMapping` (`addErrorMapping` is kept).
- PDF methods use `$this->execute(...)->body()` and void methods use `$this->execute(...)`. No new helpers.

AsaasServiceProvider: `mergeConfigFrom(__DIR__.'/../config/asaas.php', 'asaas')` in register(), unless the
compatibility effort already did it.
AsaasSdk: add `pixAutomatic` and `receivableRegistration` (Wave B owner).

Generated method shape:
```php
/**
 * @see https://docs.asaas.com/reference/estornar-cobranca
 * @param array<string, mixed> $data
 * @throws ApiError|ValidationException
 */
public function refundPayment(string $id, array $data = []): Payment
{
    $builder = (new RequestBuilder('v3/payments/{id}/refund', 'POST'))
        ->setPathParameter('id', $id)
        ->setJsonContent(RefundPaymentRequest::validate($data));
    /** @var Payment $result */
    $result = $this->executeAndMap($this->buildRequest($builder), Payment::class);
    return $result;
}
```
Constraints: PHP 8.1 syntax only (no readonly classes, DNF types, standalone true/false/null, constants in
traits); Laravel 9 Http APIs only (baseUrl, withHeaders, timeout, retry(throw:), withBody, asMultipart, verbs).

## 7. Code generation

Build it, in PHP 8.1 with no dependencies, and commit it under `tools/` with `/tools export-ignore` in a new
`.gitattributes` so it is not shipped to composer users.
- `tools/generator/generate.php`: CLI. Supports `--only=Service,...` (pilot runs) and `--check` (regenerate
  in memory and exit 1 on drift; for CI). Ports the map validation from build_endpoint_map.py.
- Separate files, each under 500 lines: `RulesBuilder.php` and one emitter each for services, requests,
  models, tests and docs.
- `tools/endpoint-map.json` is canonical. `tools/asaas-openapi.json` is the spec snapshot, which makes API
  changes diffable.
- Owns these paths (overwrites them; every generated file starts with a "generated, edit tools/endpoint-map.json"
  header):
  - src/Services/*Service.php (34 services, not BaseService)
  - the request classes referenced by the map (unreferenced ones are deleted; AsaasRequest is kept)
  - the 12 named new models
  - tests/Services/*ServiceTest.php
  - docs/services/*Service.md (PT-BR like today; titles from endpoints.tsv; docs.asaas.com/reference/<operationId> links)
- Never touches hand-written models, src/Http/*.php, AsaasSdk.php, Config, or Exceptions.
- Tests emitted per operation:
  - Http::fake with a sample response built from the response schema, then call
    `$sdk->{property}->{sdkMethod}(...)`.
  - `Http::assertSent` checks the method and the exact URL (sandbox base + encoded path + query).
  - The body is checked by type: JSON equality via `$r->data()`, multipart `isMultipart()` + part names,
    Accept header for PDF.
  - Assert the return type; inputs come from spec examples, else type defaults.
  - Files via `UploadedFile::fake()->create()`.
  - One ValidationException + `Http::assertNothingSent()` test per request class that has required fields.
- `tests/CoverageTest.php` (hand-written by A2): for every map entry, AsaasSdk has the property and the
  service has the public method with the expected parameter count and return type. This makes 213/213 a test.
- Why commit: the API keeps growing (Pix Automático is recent). Re-syncing becomes: refresh the snapshot,
  edit the map, run the generator, review the diff. build_endpoint_map.py is a one-off bootstrap and stays
  in plans/ (not committed). Also commit the spec fetch/merge script if it is small; without it the snapshot
  cannot be refreshed reproducibly.

## 8. Execution waves (max 5 agents, disjoint ownership)

Prerequisite: the Laravel 9-13 compatibility effort and the docs effort are merged (they touch composer.*,
README, docs/, tests/, and possibly AsaasHttpClient/AsaasServiceProvider). Wave A starts from that commit.

| Wave | Agent | Owns (only these files) | Done when |
|---|---|---|---|
| A (parallel) | A1 runtime | src/Http/AsaasHttpClient.php, src/Http/RequestBuilder.php, src/Services/BaseService.php, src/AsaasServiceProvider.php, src/Http/Requests/AsaasRequest.php (new), src/Models/{GenericResource,GenericList,DeleteResult}.php (new), src/Models/AccountDocumentList.php, src/Config/RetryConfig.php + config/asaas.php (only if question 1 is approved), tests/RequestBuilderTest.php, tests/Http/AsaasHttpClientTest.php (new), tests/Http/AsaasRequestTest.php (new) | §6 contract implemented; tests for multipart parts, bool query, `{}` body, rawurlencode, Accept override, PDF body, 204, bracket keys, pass-through vs strict |
| A (parallel) | A2 generator | tools/**, .gitattributes, tests/Services/ServiceTestCase.php, tests/CoverageTest.php | map validation ported; pilot `--only=PaymentRefundService,PixService,AccountDocumentService` passes php -l and its tests once A1 has landed |
| B | B1 (= A2) | src/Services/*Service.php (not BaseService), src/Http/Requests/** (not AsaasRequest), 12 new named models, tests/Services/*ServiceTest.php, docs/services/*Service.md, src/AsaasSdk.php; deletes the dead models and request classes from §3 | full generation; php -l on all files; CoverageTest + generated tests + existing tests green |
| C (parallel, read-only) | C1-C5 reviewers | only plans/review-g<N>.md | each group checked against pages/<operationId>.md: verb/path, required, enums, nested shapes, file fields, model choice, names, docs table |
| D (parallel) | D1 (= B1) | tools/**, regenerated files | review findings applied through map/generator only; regenerated; gates green |
| D (parallel) | D2 docs/release | README.md, UPGRADE.md (new), docs/README-services.md, composer.json (version 2.0.0, `"generate"` script) | docs reflect §2-§6 |

Review groups:
- G1: customer, payment, paymentWithSummaryData, paymentDocument (41 ops)
- G2: installment, subscription, paymentLink, checkout, creditCard, paymentRefund (44)
- G3: anticipation, chargeback, paymentSplit, escrowAccount, notification, creditBureauReport, paymentDunning, invoice (41)
- G4: fiscalInfo, pix, pixTransaction, pixAutomatic, recurringPix, transfer (40)
- G5: bill, mobilePhoneRecharge, financialTransaction, finance, accountInfo, webhook, subaccount, accountDocument, sandboxActions, receivableRegistration (47)

Shared-file owners:
- A1: AsaasHttpClient.php, RequestBuilder.php, BaseService.php, AsaasServiceProvider.php
- B1: AsaasSdk.php
- D2: composer.json and README.md

Nobody else edits these files.

Verification (one runner at a time, never `--parallel`, test files run one by one per the test-guard rule):
1. `php tools/generator/generate.php --check` exits 0 (map valid, no drift).
2. `php -l` on every file under src/ and tests/.
3. PHP 8.1 syntax guard: run php 8.1 `-l` if available; otherwise grep for `readonly class`, standalone
   true/false/null return types, DNF types, and constants in traits.
4. `vendor/bin/phpunit tests/<File>.php` for each touched or generated test file, one after another. Run the
   full suite only when Lucas asks.
5. The compatibility CI matrix (other effort) covers Laravel 9 and 13. The multipart part format must be
   confirmed on the Laravel 9 leg.
6. Optional sandbox smoke test (question 3).

## 9. Coordinator findings: disposition

| Finding | Decision |
|---|---|
| ListPaymentsRequest paymentDateGe/Le vs `paymentDate[ge]/[le]` | Fixed by regeneration from the spec. Bracket keys are kept verbatim and the old keys are now rejected as unknown query keys (UPGRADE note). |
| Store/UpdatePaymentRequest discount/fine numeric, interest integer | Fixed by regeneration: they are now objects, validated with dot notation. |
| validate() drops keys without a rule | Decided: bodies pass through (validate known fields, send everything); queries are strict (unknown key is a ValidationException). |
| RequestBuilder path params not encoded | Fixed in A1 (rawurlencode). |
| ConnectionException not wrapped in ApiError | Out of scope (not spec-driven, no data loss). D2 documents it in README; follow-up if wanted. |
| Also found | bool query sent as `1`; null body sent as `[]`; Accept/Content-Type forced to JSON; fiscalInfo.list mapped a single object onto a list; AccountDocumentList drops `rejectReasons`. All fixed in A1/B. |

## 10. Risks

- The merged spec was assembled from 213 page-level specs; same-named schemas may have been collapsed.
  Wave C review against pages/*.md is the mitigation.
- POST retry: RetryConfig retries POST on 408/429/5xx and on connection errors. New money-moving operations
  (transfer.create, bill.create, pixTransaction.payQrCode, mobilePhoneRecharge.create, refunds) could run
  twice after a timeout. See question 1.
- Multi-file field encoding (repeated `files` vs `files[]`) and the trailing-slash variants can only be
  confirmed against the real API.
- docs/services/*.md become generated, so the docs effort's edits there will be overwritten. Hand-written
  prose goes in README / docs/README-services.md.
- Strict query keys reject filters Asaas adds later until the map is updated (re-sync is a generator run).

## 11. Open questions for the user

1. Remove POST from the default retried methods in 2.0.0 (RetryConfig + config/asaas.php)? Recommended.
2. Ship as 2.0.0 (recommended), and release the Laravel 9-13 compatibility work first as 1.1.0?
3. Is a sandbox API key available for a live smoke run (multipart, PDF, 204, bracket filters, trailing-slash variants)?

## 12. User decisions (2026-11-10)

1. Retry: POST removed from the default retried methods in 2.0.0 (RetryConfig default + config/asaas.php). Opt-in via `asaas.retry.methods`. UPGRADE.md note required.
2. Versioning: ship the Laravel 9-13 / PHP 8.1-8.5 compatibility work first as 1.1.0 (no breaking changes), then this effort as 2.0.0.
3. Sandbox: Lucas will provide a sandbox ASAAS_API_KEY via env at the end. Smoke test runs only GETs and sandbox actions; never commit the key.
4. PHP support ceiling: 8.5.
5. AI usage (wave D, after the API is final):
   - consumer skill shipped with the package at `resources/boost/skills/asaas-sdk-laravel/SKILL.md` (Laravel Boost package-skill format), with the per-service reference emitted by the generator;
   - `docs/ai-playbook.md`: task recipes for AI agents (charge, Pix, subscription, split, webhook handling, error/retry handling, testing with Http::fake);
   - maintainer skill for re-syncing the SDK with the official API through tools/generator.
