# SubscriptionService

## Propósito

Serviço que encapsula o recurso **Assinaturas** da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/subscriptions`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\SubscriptionService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista assinaturas com paginação e filtros | `array $params = []` (query) | `SubscriptionList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListSubscriptionsRequest::validate($params)`.

### Models / Requests

- **Models:** `Subscription`, `SubscriptionList`
- **Requests:** `ListSubscriptionsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->subscription;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Assinaturas** / **Subscriptions**.
