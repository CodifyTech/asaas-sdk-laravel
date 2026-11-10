# PaymentWithSummaryDataService

## Propósito

Serviço que encapsula o recurso **Pagamentos com dados resumidos** da API Asaas. Permite listar. no path `v3/payments/summary`.

---

## Endpoint base

- **API Asaas:** `v3/payments/summary`

> **Atenção:** o caminho `GET v3/payments/summary` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `GET v3/lean/payments`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\PaymentWithSummaryDataService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista pagamentos com dados resumidos | `array $params = []` (query) | `PaymentWithSummaryList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListPaymentWithSummaryRequest::validate($params)`.

### Models / Requests

- **Models:** `PaymentWithSummary`, `PaymentWithSummaryList`
- **Requests:** `ListPaymentWithSummaryRequest`

### Exemplo (Laravel)

```php
$service = asaas()->paymentWithSummaryData;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Pagamentos** / **Payments** (lean/summary).
