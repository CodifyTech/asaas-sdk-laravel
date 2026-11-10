# AccountInfoService

## Propósito

Serviço que encapsula o recurso **Informações da conta** (myAccount) da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/myAccount`

> **Atenção:** o caminho `GET v3/myAccount` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `GET v3/myAccount/commercialInfo/`
> - `GET v3/myAccount/status/`
> - `GET v3/myAccount/accountNumber`
> - `GET v3/myAccount/fees/`
> - `GET v3/wallets/`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\AccountInfoService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista informações da conta | `array $params = []` (query) | `AccountInfoList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListAccountInfoRequest::validate($params)`.

### Models / Requests

- **Models:** `AccountInfo`, `AccountInfoList`
- **Requests:** `ListAccountInfoRequest`

### Exemplo (Laravel)

```php
$service = asaas()->accountInfo;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Minha conta** / **My Account**.
