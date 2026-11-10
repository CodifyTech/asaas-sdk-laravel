# PaymentLinkService

## Propósito

Serviço que encapsula o recurso **Links de pagamento** da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/paymentLinks`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\PaymentLinkService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista links de pagamento | `array $params = []` (query) | `PaymentLinkList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListPaymentLinksRequest::validate($params)`.

### Models / Requests

- **Models:** `PaymentLink`, `PaymentLinkList`
- **Requests:** `ListPaymentLinksRequest`

### Exemplo (Laravel)

```php
$service = asaas()->paymentLink;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Links de pagamento**.
