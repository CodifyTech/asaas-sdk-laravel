# RecurringPixService

## Propósito

Serviço que encapsula o recurso **PIX recorrente** da API Asaas. Permite listar. em `v3/recurringPix`.

---

## Endpoint base

- **API Asaas:** `v3/recurringPix`

> **Atenção:** o caminho `GET v3/recurringPix` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `GET v3/pix/transactions/recurrings`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\RecurringPixService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista PIX recorrentes | `array $params = []` (query) | `RecurringPixList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListRecurringPixRequest::validate($params)`.

### Models / Requests

- **Models:** `RecurringPix`, `RecurringPixList`
- **Requests:** `ListRecurringPixRequest`

### Exemplo (Laravel)

```php
$service = asaas()->recurringPix;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **PIX recorrente**.
