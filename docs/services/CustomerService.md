# CustomerService

## Propósito

Serviço que encapsula o recurso **Clientes** da API Asaas. Permite listar, criar, obter, atualizar e remover clientes (customers), além de restaurar clientes removidos e listar notificações de um cliente.

---

## Endpoint base

- **API Asaas:** `v3/customers`

---

## Laravel (asaas-api-sdk-laravel)

### Classe

- **Namespace e classe:** `Asaas\Laravel\Services\CustomerService`

### Métodos

| Método | HTTP | Descrição | Parâmetros | Retorno |
|--------|------|-----------|------------|---------|
| `list` | GET | Lista clientes com paginação e filtros | `array $params = []` (query) | `CustomerList` |
| `create` | POST | Cria um novo cliente | `array $data` (body) | `Customer` |
| `get` | GET | Obtém um cliente pelo ID | `string $id` | `Customer` |
| `update` | PUT | Atualiza um cliente existente | `string $id`, `array $data` (body) | `Customer` |
| `delete` | DELETE | Remove um cliente | `string $id` | `CustomerDeleteResult` |

### Parâmetros (query/body)

**Listagem (`list`):** parâmetros de query validados por `ListCustomersRequest::validate($params)`:

- `offset` (nullable, integer, min: 0)
- `limit` (nullable, integer, min: 1, max: 100)
- `name` (nullable, string, max: 100)
- `email` (nullable, string, max: 100)
- `cpfCnpj` (nullable, string, max: 18)
- `groupName` (nullable, string, max: 100)
- `externalReference` (nullable, string, max: 100)

**Criação (`create`):** body validado por `StoreCustomerRequest::validate($data)`.

**Atualização (`update`):** body validado por `UpdateCustomerRequest::validate($data)`.

### Models / Requests

- **Models:** `Customer`, `CustomerList`, `CustomerDeleteResult`
- **Requests:** `StoreCustomerRequest`, `UpdateCustomerRequest`, `ListCustomersRequest`

### Exemplo (Laravel)

```php
$customerService = asaas()->customer;

// list
$list = $customerService->list(['limit' => 10, 'offset' => 0, 'email' => 'cliente@example.com']);

// create
$customer = $customerService->create([
 'name' => 'João Silva',
 'cpfCnpj' => '12345678909',
 'email' => 'joao@example.com',
]);

// get
$customer = $customerService->get($customer->id);

// update
$customer = $customerService->update($customer->id, ['name' => 'João Silva Atualizado']);

// delete
$result = $customerService->delete($customer->id);
```


---

## Referência API Asaas

- Documentação do recurso Clientes: [https://docs.asaas.com](https://docs.asaas.com) — consulte a seção referente a **Clientes** ou **Customers** para parâmetros oficiais de listagem, criação e atualização.
