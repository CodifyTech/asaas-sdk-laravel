# BillService

## Propósito

Serviço que encapsula o recurso **Contas a pagar** (bills) da API Asaas. Permite listar contas.

---

## Endpoint base

- **API Asaas:** `v3/bills`

> **Atenção:** o caminho `GET v3/bills` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `GET v3/bill`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\BillService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista contas a pagar com paginação e filtros | `array $params = []` (query) | `BillList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListBillsRequest::validate($params)`.

### Models / Requests

- **Models:** `Bill`, `BillList`
- **Requests:** `ListBillsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->bill;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Contas a pagar** / **Bills**.
