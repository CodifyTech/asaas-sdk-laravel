# CheckoutService

## Propósito

Serviço que encapsula o recurso **Checkout** da API Asaas. Permite listar sessões de checkout.

---

## Endpoint base

- **API Asaas:** `v3/checkout`

> **Atenção:** o caminho `GET v3/checkout` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Não há listagem oficial para este recurso; os endpoints existentes na referência são:
> - `POST v3/checkouts`
> - `POST v3/checkouts/{id}/cancel`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\CheckoutService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista checkouts com paginação e filtros | `array $params = []` (query) | `CheckoutList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListCheckoutRequest::validate($params)`.

### Models / Requests

- **Models:** `Checkout`, `CheckoutList`
- **Requests:** `ListCheckoutRequest`

### Exemplo (Laravel)

```php
$service = asaas()->checkout;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Checkout**.
