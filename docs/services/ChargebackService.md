# ChargebackService

## Propósito

Serviço que encapsula o recurso **Chargebacks** da API Asaas. Permite listar chargebacks.

---

## Endpoint base

- **API Asaas:** `v3/chargebacks`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\ChargebackService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista chargebacks com paginação e filtros | `array $params = []` (query) | `ChargebackList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListChargebacksRequest::validate($params)`.

### Models / Requests

- **Models:** `Chargeback`, `ChargebackList`
- **Requests:** `ListChargebacksRequest`

### Exemplo (Laravel)

```php
$service = asaas()->chargeback;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Chargebacks**.
