# InvoiceService

## Propósito

Serviço que encapsula o recurso **Notas fiscais / Faturas** (invoices) da API Asaas. Permite listar faturas.

---

## Endpoint base

- **API Asaas:** `v3/invoices`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\InvoiceService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista faturas com paginação e filtros | `array $params = []` (query) | `InvoiceList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListInvoicesRequest::validate($params)`.

### Models / Requests

- **Models:** `Invoice`, `InvoiceList`
- **Requests:** `ListInvoicesRequest`

### Exemplo (Laravel)

```php
$service = asaas()->invoice;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Faturas** / **Invoices**.
