# PixTransactionService

## Propósito

Serviço que encapsula o recurso **Transações PIX** da API Asaas. Permite listar. em `v3/pix/transactions`.

---

## Endpoint base

- **API Asaas:** `v3/pix/transactions`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\PixTransactionService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista transações PIX | `array $params = []` (query) | `PixTransactionList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListPixTransactionsRequest::validate($params)`.

### Models / Requests

- **Models:** `PixTransaction`, `PixTransactionList`
- **Requests:** `ListPixTransactionsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->pixTransaction;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Transações PIX**.
