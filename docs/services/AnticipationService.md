# AnticipationService

## Propósito

Serviço que encapsula o recurso **Antecipações** da API Asaas. Permite listar antecipações de recebíveis.

---

## Endpoint base

- **API Asaas:** `v3/anticipations`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\AnticipationService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista antecipações com paginação e filtros | `array $params = []` (query) | `AnticipationList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListAnticipationsRequest::validate($params)`.

### Models / Requests

- **Models:** `Anticipation`, `AnticipationList`
- **Requests:** `ListAnticipationsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->anticipation;
$list = $service->list(['limit' => 10, 'offset' => 0]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Antecipações**.
