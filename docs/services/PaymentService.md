# PaymentService

## Propósito

Serviço que encapsula o recurso **Cobranças (Payments)** da API Asaas. Permite listar, criar, obter, atualizar e remover cobranças.

---

## Endpoint base

- **API Asaas:** `v3/payments`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\PaymentService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista cobranças com paginação e filtros | `array $params = []` (query) | `PaymentList` |
| `create` | POST | Cria uma nova cobrança | `array $data` (body) | `Payment` |
| `get` | GET | Obtém uma cobrança pelo ID | `string $id` | `Payment` |
| `update` | PUT | Atualiza uma cobrança existente | `string $id`, `array $data` (body) | `Payment` |
| `delete` | DELETE | Remove uma cobrança | `string $id` | `PaymentDeleteResult` |

### Parâmetros (query/body)

**Listagem (`list`):** parâmetros de query validados por `ListPaymentsRequest::validate($params)` (offset, limit, customer, billingType, status, subscription, externalReference, paymentDate, etc.).

**Criação (`create`):** body validado por `StorePaymentRequest::validate($data)`.

**Atualização (`update`):** body validado por `UpdatePaymentRequest::validate($data)`.

### Models / Requests

- **Models:** `Payment`, `PaymentList`, `PaymentDeleteResult`
- **Requests:** `StorePaymentRequest`, `UpdatePaymentRequest`, `ListPaymentsRequest`

### Exemplo (Laravel)

```php
$paymentService = asaas()->payment;

// list
$list = $paymentService->list(['limit' => 10, 'offset' => 0, 'customer' => 'cus_xxx']);

// create
$payment = $paymentService->create([
 'customer' => 'cus_xxx',
 'billingType' => 'BOLETO',
 'value' => 100.00,
 'dueDate' => '2025-03-15',
]);

// get
$payment = $paymentService->get($payment->id);

// update
$payment = $paymentService->update($payment->id, ['value' => 150.00]);

// delete
$result = $paymentService->delete($payment->id);
```


---

## Referência API Asaas

- Documentação do recurso Cobranças: [https://docs.asaas.com](https://docs.asaas.com) — consulte a seção **Cobranças** ou **Payments** para parâmetros oficiais de listagem, criação, atualização e operações adicionais (reembolso, PIX, etc.).
