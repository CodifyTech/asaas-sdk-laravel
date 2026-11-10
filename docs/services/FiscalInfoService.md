# FiscalInfoService

## Propósito

Serviço que encapsula o recurso **Informações fiscais** da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/fiscalInfo`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\FiscalInfoService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista informações fiscais | `array $params = []` (query) | `FiscalInfoList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListFiscalInfoRequest::validate($params)`.

### Models / Requests

- **Models:** `FiscalInfo`, `FiscalInfoList`
- **Requests:** `ListFiscalInfoRequest`

### Exemplo (Laravel)

```php
$service = asaas()->fiscalInfo;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Informações fiscais**.
