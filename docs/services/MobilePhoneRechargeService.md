# MobilePhoneRechargeService

## Propósito

Serviço que encapsula o recurso **Recarga de celular** da API Asaas. Permite listar recargas.

---

## Endpoint base

- **API Asaas:** `v3/mobilePhoneRecharge`

> **Atenção:** o caminho `GET v3/mobilePhoneRecharge` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `GET v3/mobilePhoneRecharges`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\MobilePhoneRechargeService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista recargas de celular | `array $params = []` (query) | `MobilePhoneRechargeList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListMobilePhoneRechargeRequest::validate($params)`.

### Models / Requests

- **Models:** `MobilePhoneRecharge`, `MobilePhoneRechargeList`
- **Requests:** `ListMobilePhoneRechargeRequest`

### Exemplo (Laravel)

```php
$service = asaas()->mobilePhoneRecharge;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Recarga de celular**.
