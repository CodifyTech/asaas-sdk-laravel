# SubaccountService

## Propósito

Serviço que encapsula o recurso **Subcontas** (accounts) da API Asaas. Permite listar. em `v3/accounts`.

---

## Endpoint base

- **API Asaas:** `v3/accounts`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\SubaccountService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista subcontas | `array $params = []` (query) | `SubaccountList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListSubaccountsRequest::validate($params)`.

### Models / Requests

- **Models:** `Subaccount`, `SubaccountList`
- **Requests:** `ListSubaccountsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->subaccount;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Subcontas** / **Accounts**.
