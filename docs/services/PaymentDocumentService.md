# PaymentDocumentService

## Propósito

Serviço que encapsula o recurso **Documentos de pagamento** da API Asaas. Permite listar.

---

## Endpoint base

- **API Asaas:** `v3/paymentDocuments`

> **Atenção:** o caminho `GET v3/paymentDocuments` chamado por este serviço não consta na referência oficial da API Asaas (verificada em 2026-11-10). Não há garantia de que `list()` funcione; a API pode responder com erro HTTP (por exemplo 404, lançado como `ApiError`). Endpoints oficiais do recurso, ainda não implementados neste SDK:
> - `GET v3/payments/{id}/documents`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\PaymentDocumentService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista documentos de pagamento | `array $params = []` (query) | `PaymentDocumentList` |

### Parâmetros (query/body)

**Listagem:** validados por `ListPaymentDocumentsRequest::validate($params)`.

### Models / Requests

- **Models:** `PaymentDocument`, `PaymentDocumentList`
- **Requests:** `ListPaymentDocumentsRequest`

### Exemplo (Laravel)

```php
$service = asaas()->paymentDocument;
$list = $service->list(['limit' => 10]);
```


---

## Referência API Asaas

- [Documentação Asaas](https://docs.asaas.com) — recurso **Documentos de pagamento**.
