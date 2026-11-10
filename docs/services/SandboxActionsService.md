# SandboxActionsService

## Propósito

Serviço que encapsula as **Ações de sandbox** da API Asaas para ambiente de testes. Permite listar ações disponíveis.

---

## Endpoint base

- **API Asaas:** `v3/sandbox`

> **Atenção:** o caminho `GET v3/sandbox` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Não há listagem oficial para este recurso; os endpoints existentes na referência são:
> - `POST v3/sandbox/myAccount/approve`
> - `POST v3/sandbox/payment/{id}/confirm`
> - `POST v3/sandbox/payment/{id}/overdue`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\SandboxActionsService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista ações de sandbox disponíveis | `array $params = []` (query) | `SandboxActionList` |

### Parâmetros (query/body)

**Listagem:** conforme API (quando aplicável); pode não seguir paginação padrão.

### Models / Requests

- **Models:** `SandboxAction`, `SandboxActionList`
- **Requests:** `ListSandboxActionsRequest` (se aplicável)

### Exemplo (Laravel)

```php
$service = asaas()->sandboxActions;
$list = $service->list([]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Sandbox** / **Ambiente de testes**.
