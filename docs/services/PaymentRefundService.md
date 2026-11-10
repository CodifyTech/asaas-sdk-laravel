# PaymentRefundService

## Propósito

Serviço que encapsula o recurso **Reembolsos** de pagamentos da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/payments/refunds`

> **Atenção:** o caminho `GET v3/payments/refunds` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `GET v3/payments/{id}/refunds`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\PaymentRefundService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista reembolsos | `array $params = []` (query) | `PaymentRefundList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListPaymentRefundsRequest::validate($params)`.

### Models / Requests

- **Models:** `PaymentRefund`, `PaymentRefundList`
- **Requests:** `ListPaymentRefundsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->paymentRefund;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Reembolsos** / **Refunds**.
