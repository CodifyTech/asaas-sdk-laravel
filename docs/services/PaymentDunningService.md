# PaymentDunningService

## Propósito

Serviço que encapsula o recurso **Inadimplência** (payment dunning) da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/paymentDunnings`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\PaymentDunningService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista negociações de inadimplência | `array $params = []` (query) | `PaymentDunningList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListPaymentDunningRequest::validate($params)`.

### Models / Requests

- **Models:** `PaymentDunning`, `PaymentDunningList`
- **Requests:** `ListPaymentDunningRequest`

### Exemplo (Laravel)

```php
$service = asaas()->paymentDunning;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Inadimplência** / **Dunning**.
