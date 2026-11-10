# AccountDocumentService

## Propósito

Serviço que encapsula o recurso **Documentos da conta** da API Asaas. Permite listar e gerenciar documentos da conta (myAccount/documents).

---

## Endpoint base

- **API Asaas:** `v3/myAccount/documents`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\AccountDocumentService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista documentos da conta com paginação e filtros | `array $params = []` (query) | `AccountDocumentList` |

### Parâmetros (query/body)

**Listagem (`list`):** parâmetros validados por `ListAccountDocumentsRequest::validate($params)` (offset, limit e filtros conforme API Asaas).

### Models / Requests

- **Models:** `AccountDocument`, `AccountDocumentList`
- **Requests:** `ListAccountDocumentsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->accountDocument;
$list = $service->list(['limit' => 10, 'offset' => 0]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Documentos da conta** / **My Account Documents**.
