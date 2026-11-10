# WebhookService

## Propósito

Serviço que encapsula o recurso **Webhooks** da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/webhooks`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\WebhookService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista webhooks com paginação e filtros | `array $params = []` (query) | `WebhookList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListWebhooksRequest::validate($params)`.

### Models / Requests

- **Models:** `Webhook`, `WebhookList`
- **Requests:** `ListWebhooksRequest`

### Exemplo (Laravel)

```php
$service = asaas()->webhook;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Webhooks**.
