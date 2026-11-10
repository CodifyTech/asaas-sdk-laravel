<?php

declare(strict_types=1);

namespace Asaas\Laravel;

use Asaas\Laravel\Config\AsaasSdkConfig;
use Asaas\Laravel\Http\AsaasHttpClient;
use Asaas\Laravel\Services\AccountDocumentService;
use Asaas\Laravel\Services\AccountInfoService;
use Asaas\Laravel\Services\AnticipationService;
use Asaas\Laravel\Services\BillService;
use Asaas\Laravel\Services\ChargebackService;
use Asaas\Laravel\Services\CheckoutService;
use Asaas\Laravel\Services\CreditBureauReportService;
use Asaas\Laravel\Services\CreditCardService;
use Asaas\Laravel\Services\CustomerService;
use Asaas\Laravel\Services\EscrowAccountService;
use Asaas\Laravel\Services\FinanceService;
use Asaas\Laravel\Services\FinancialTransactionService;
use Asaas\Laravel\Services\FiscalInfoService;
use Asaas\Laravel\Services\InstallmentService;
use Asaas\Laravel\Services\InvoiceService;
use Asaas\Laravel\Services\MobilePhoneRechargeService;
use Asaas\Laravel\Services\NotificationService;
use Asaas\Laravel\Services\PaymentDocumentService;
use Asaas\Laravel\Services\PaymentDunningService;
use Asaas\Laravel\Services\PaymentLinkService;
use Asaas\Laravel\Services\PaymentRefundService;
use Asaas\Laravel\Services\PaymentService;
use Asaas\Laravel\Services\PaymentSplitService;
use Asaas\Laravel\Services\PaymentWithSummaryDataService;
use Asaas\Laravel\Services\PixAutomaticService;
use Asaas\Laravel\Services\PixService;
use Asaas\Laravel\Services\PixTransactionService;
use Asaas\Laravel\Services\ReceivableRegistrationService;
use Asaas\Laravel\Services\RecurringPixService;
use Asaas\Laravel\Services\SandboxActionsService;
use Asaas\Laravel\Services\SubaccountService;
use Asaas\Laravel\Services\SubscriptionService;
use Asaas\Laravel\Services\TransferService;
use Asaas\Laravel\Services\WebhookService;

final class AsaasSdk
{
    public readonly PaymentService $payment;

    public readonly SandboxActionsService $sandboxActions;

    public readonly PaymentWithSummaryDataService $paymentWithSummaryData;

    public readonly CreditCardService $creditCard;

    public readonly PaymentRefundService $paymentRefund;

    public readonly PaymentSplitService $paymentSplit;

    public readonly EscrowAccountService $escrowAccount;

    public readonly PaymentDocumentService $paymentDocument;

    public readonly CustomerService $customer;

    public readonly NotificationService $notification;

    public readonly InstallmentService $installment;

    public readonly SubscriptionService $subscription;

    public readonly PixService $pix;

    public readonly PixTransactionService $pixTransaction;

    public readonly AnticipationService $anticipation;

    public readonly RecurringPixService $recurringPix;

    public readonly PaymentLinkService $paymentLink;

    public readonly CheckoutService $checkout;

    public readonly TransferService $transfer;

    public readonly PaymentDunningService $paymentDunning;

    public readonly BillService $bill;

    public readonly MobilePhoneRechargeService $mobilePhoneRecharge;

    public readonly CreditBureauReportService $creditBureauReport;

    public readonly FinancialTransactionService $financialTransaction;

    public readonly FinanceService $finance;

    public readonly AccountInfoService $accountInfo;

    public readonly InvoiceService $invoice;

    public readonly FiscalInfoService $fiscalInfo;

    public readonly WebhookService $webhook;

    public readonly SubaccountService $subaccount;

    public readonly AccountDocumentService $accountDocument;

    public readonly ChargebackService $chargeback;

    public readonly PixAutomaticService $pixAutomatic;

    public readonly ReceivableRegistrationService $receivableRegistration;

    public function __construct(
        private readonly AsaasSdkConfig $config,
        ?AsaasHttpClient $httpClient = null,
    ) {
        $client = $httpClient ?? new AsaasHttpClient($config);

        $this->payment = new PaymentService($client, $config);
        $this->sandboxActions = new SandboxActionsService($client, $config);
        $this->paymentWithSummaryData = new PaymentWithSummaryDataService($client, $config);
        $this->creditCard = new CreditCardService($client, $config);
        $this->paymentRefund = new PaymentRefundService($client, $config);
        $this->paymentSplit = new PaymentSplitService($client, $config);
        $this->escrowAccount = new EscrowAccountService($client, $config);
        $this->paymentDocument = new PaymentDocumentService($client, $config);
        $this->customer = new CustomerService($client, $config);
        $this->notification = new NotificationService($client, $config);
        $this->installment = new InstallmentService($client, $config);
        $this->subscription = new SubscriptionService($client, $config);
        $this->pix = new PixService($client, $config);
        $this->pixTransaction = new PixTransactionService($client, $config);
        $this->anticipation = new AnticipationService($client, $config);
        $this->recurringPix = new RecurringPixService($client, $config);
        $this->paymentLink = new PaymentLinkService($client, $config);
        $this->checkout = new CheckoutService($client, $config);
        $this->transfer = new TransferService($client, $config);
        $this->paymentDunning = new PaymentDunningService($client, $config);
        $this->bill = new BillService($client, $config);
        $this->mobilePhoneRecharge = new MobilePhoneRechargeService($client, $config);
        $this->creditBureauReport = new CreditBureauReportService($client, $config);
        $this->financialTransaction = new FinancialTransactionService($client, $config);
        $this->finance = new FinanceService($client, $config);
        $this->accountInfo = new AccountInfoService($client, $config);
        $this->invoice = new InvoiceService($client, $config);
        $this->fiscalInfo = new FiscalInfoService($client, $config);
        $this->webhook = new WebhookService($client, $config);
        $this->subaccount = new SubaccountService($client, $config);
        $this->accountDocument = new AccountDocumentService($client, $config);
        $this->chargeback = new ChargebackService($client, $config);
        $this->pixAutomatic = new PixAutomaticService($client, $config);
        $this->receivableRegistration = new ReceivableRegistrationService($client, $config);
    }

    /**
     * @param  array<string, mixed>  $config  the `asaas` config array
     *
     * @throws \InvalidArgumentException on an invalid environment, base URL, timeout or retry setting
     */
    public static function fromConfig(#[\SensitiveParameter] array $config): self
    {
        return new self(AsaasSdkConfig::fromArray($config));
    }
}
