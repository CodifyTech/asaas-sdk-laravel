# CreditCardService

## Propósito

Serviço que encapsula o recurso **Cartão de crédito** da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/creditCard`

> **Atenção:** o caminho `GET v3/creditCard` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `POST v3/creditCard/tokenizeCreditCard`
> - `GET v3/creditCard/preAuthorization/config`
> - `POST v3/creditCard/preAuthorization/config`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\CreditCardService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista recursos de cartão (conforme API) | `array $params = []` (query) | `CreditCardList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListCreditCardRequest::validate($params)`.

### Models / Requests

- **Models:** `CreditCard`, `CreditCardList`
- **Requests:** `ListCreditCardRequest`

### Exemplo (Laravel)

```php
$service = asaas()->creditCard;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Cartão de crédito** / **Tokenização**.
