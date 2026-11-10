# NotificationService

## Propósito

Serviço que encapsula o recurso **Notificações** da API Asaas. Permite listar notificações.

---

## Endpoint base

- **API Asaas:** `v3/notifications`

> **Atenção:** o caminho `GET v3/notifications` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Não há listagem oficial para este recurso; os endpoints existentes na referência são:
> - `GET v3/customers/{id}/notifications`
> - `PUT v3/notifications/{id}`
> - `PUT v3/notifications/batch`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\NotificationService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista notificações com paginação e filtros | `array $params = []` (query) | `NotificationList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListNotificationsRequest::validate($params)`.

### Models / Requests

- **Models:** `Notification`, `NotificationList`
- **Requests:** `ListNotificationsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->notification;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Notificações**.
