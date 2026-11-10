# FinancialTransactionService

## Propósito

Serviço que encapsula o recurso **Transações financeiras** da API Asaas. Permite listar transações financeiras com paginação e filtros.

---

## Endpoint base

- **API Asaas:** `v3/financialTransactions`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\FinancialTransactionService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista transações financeiras | `array $params = []` (query) | `FinancialTransactionList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListFinancialTransactionsRequest::validate($params)`.

### Models / Requests

- **Models:** `FinancialTransaction`, `FinancialTransactionList`
- **Requests:** `ListFinancialTransactionsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->financialTransaction;
$list = $service->list(['limit' => 10, 'offset' => 0]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Transações financeiras**.
