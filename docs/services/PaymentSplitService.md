# PaymentSplitService

## Propósito

Serviço que encapsula o recurso **Split de pagamentos** da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/paymentSplits`

> **Atenção:** o caminho `GET v3/paymentSplits` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `GET v3/payments/splits/paid`
> - `GET v3/payments/splits/received`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\PaymentSplitService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista splits de pagamento | `array $params = []` (query) | `PaymentSplitList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListPaymentSplitsRequest::validate($params)`.

### Models / Requests

- **Models:** `PaymentSplit`, `PaymentSplitList`
- **Requests:** `ListPaymentSplitsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->paymentSplit;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Split de pagamentos**.
