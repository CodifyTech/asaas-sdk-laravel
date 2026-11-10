# EscrowAccountService

## Propósito

Serviço que encapsula o recurso **Contas em garantia (escrow)** da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/escrow`

> **Atenção:** o caminho `GET v3/escrow` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `GET v3/accounts/escrow`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\EscrowAccountService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista contas escrow com paginação e filtros | `array $params = []` (query) | `EscrowAccountList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListEscrowAccountsRequest::validate($params)`.

### Models / Requests

- **Models:** `EscrowAccount`, `EscrowAccountList`
- **Requests:** `ListEscrowAccountsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->escrowAccount;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Escrow** / **Contas em garantia**.
