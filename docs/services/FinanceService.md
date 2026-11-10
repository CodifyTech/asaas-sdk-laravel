# FinanceService

## Propósito

Serviço que encapsula o recurso **Financeiro** da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/finances`

> **Atenção:** o caminho `GET v3/finances` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `GET v3/finance/balance`
> - `GET v3/finance/payment/statistics`
> - `GET v3/finance/split/statistics`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\FinanceService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista dados financeiros | `array $params = []` (query) | `FinanceList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListFinanceRequest::validate($params)`.

### Models / Requests

- **Models:** `Finance`, `FinanceList`
- **Requests:** `ListFinanceRequest`

### Exemplo (Laravel)

```php
$service = asaas()->finance;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Financeiro** / **Finance**.
