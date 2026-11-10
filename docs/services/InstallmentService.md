# InstallmentService

## Propósito

Serviço que encapsula o recurso **Parcelamentos** (installments) da API Asaas. Permite listar parcelamentos.

---

## Endpoint base

- **API Asaas:** `v3/installments`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\InstallmentService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista parcelamentos com paginação e filtros | `array $params = []` (query) | `InstallmentList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListInstallmentsRequest::validate($params)`.

### Models / Requests

- **Models:** `Installment`, `InstallmentList`
- **Requests:** `ListInstallmentsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->installment;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Parcelamentos** / **Installments**.
