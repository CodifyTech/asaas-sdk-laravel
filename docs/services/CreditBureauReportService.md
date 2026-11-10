# CreditBureauReportService

## Propósito

Serviço que encapsula o recurso **Relatórios de bureau de crédito** da API Asaas. Permite listar relatórios.

---

## Endpoint base

- **API Asaas:** `v3/creditBureauReport`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\CreditBureauReportService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista relatórios de bureau de crédito | `array $params = []` (query) | `CreditBureauReportList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListCreditBureauReportsRequest::validate($params)`.

### Models / Requests

- **Models:** `CreditBureauReport`, `CreditBureauReportList`
- **Requests:** `ListCreditBureauReportsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->creditBureauReport;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Relatório de bureau de crédito**.
