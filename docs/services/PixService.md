# PixService

## Propósito

Serviço que encapsula o recurso **PIX** da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/pix`

> **Atenção:** o caminho `GET v3/pix` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `GET v3/pix/addressKeys`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\PixService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista recursos PIX | `array $params = []` (query) | `PixList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListPixRequest::validate($params)`.

### Models / Requests

- **Models:** `Pix`, `PixList`
- **Requests:** `ListPixRequest`

### Exemplo (Laravel)

```php
$service = asaas()->pix;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **PIX**.
