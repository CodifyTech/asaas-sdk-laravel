# Índice de Serviços — SDK Asaas (Laravel)

Documentação em Markdown de todos os serviços do SDK **asaas-api-sdk-laravel** (PHP/Laravel). Cada serviço possui um documento com propósito, endpoint base, métodos, parâmetros, exemplos e referência à API Asaas.

**Total:** 32 serviços, mais o `BaseService` (contrato interno).

---

## Lista de serviços

| # | Serviço | Documento |
|---|---------|------------|
| 1 | BaseService | [BaseService.md](services/BaseService.md) |
| 2 | AccountDocumentService | [AccountDocumentService.md](services/AccountDocumentService.md) |
| 3 | AccountInfoService | [AccountInfoService.md](services/AccountInfoService.md) |
| 4 | AnticipationService | [AnticipationService.md](services/AnticipationService.md) |
| 5 | BillService | [BillService.md](services/BillService.md) |
| 6 | ChargebackService | [ChargebackService.md](services/ChargebackService.md) |
| 7 | CheckoutService | [CheckoutService.md](services/CheckoutService.md) |
| 8 | CreditBureauReportService | [CreditBureauReportService.md](services/CreditBureauReportService.md) |
| 9 | CreditCardService | [CreditCardService.md](services/CreditCardService.md) |
| 10 | CustomerService | [CustomerService.md](services/CustomerService.md) |
| 11 | EscrowAccountService | [EscrowAccountService.md](services/EscrowAccountService.md) |
| 12 | FinancialTransactionService | [FinancialTransactionService.md](services/FinancialTransactionService.md) |
| 13 | FinanceService | [FinanceService.md](services/FinanceService.md) |
| 14 | FiscalInfoService | [FiscalInfoService.md](services/FiscalInfoService.md) |
| 15 | InstallmentService | [InstallmentService.md](services/InstallmentService.md) |
| 16 | InvoiceService | [InvoiceService.md](services/InvoiceService.md) |
| 17 | MobilePhoneRechargeService | [MobilePhoneRechargeService.md](services/MobilePhoneRechargeService.md) |
| 18 | NotificationService | [NotificationService.md](services/NotificationService.md) |
| 19 | PaymentDocumentService | [PaymentDocumentService.md](services/PaymentDocumentService.md) |
| 20 | PaymentDunningService | [PaymentDunningService.md](services/PaymentDunningService.md) |
| 21 | PaymentLinkService | [PaymentLinkService.md](services/PaymentLinkService.md) |
| 22 | PaymentRefundService | [PaymentRefundService.md](services/PaymentRefundService.md) |
| 23 | PaymentService | [PaymentService.md](services/PaymentService.md) |
| 24 | PaymentSplitService | [PaymentSplitService.md](services/PaymentSplitService.md) |
| 25 | PaymentWithSummaryDataService | [PaymentWithSummaryDataService.md](services/PaymentWithSummaryDataService.md) |
| 26 | PixService | [PixService.md](services/PixService.md) |
| 27 | PixTransactionService | [PixTransactionService.md](services/PixTransactionService.md) |
| 28 | RecurringPixService | [RecurringPixService.md](services/RecurringPixService.md) |
| 29 | SandboxActionsService | [SandboxActionsService.md](services/SandboxActionsService.md) |
| 30 | SubaccountService | [SubaccountService.md](services/SubaccountService.md) |
| 31 | SubscriptionService | [SubscriptionService.md](services/SubscriptionService.md) |
| 32 | TransferService | [TransferService.md](services/TransferService.md) |
| 33 | WebhookService | [WebhookService.md](services/WebhookService.md) |

---

## Convenções

- **Um arquivo por serviço:** `docs/services/<ServiceName>.md`.
- **BaseService:** documenta o contrato base (execute, executeAndMap, buildRequest, mapeamento de erros).
- **Estrutura de cada doc:** Propósito, Endpoint base, Laravel (classe, métodos, parâmetros, Models/Requests, exemplo), Referência API Asaas.

Ver [PLAN.md](PLAN.md) e [services-inventory.md](services-inventory.md) para detalhes do plano e inventário de endpoints/métodos.
