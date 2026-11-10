<?php

declare(strict_types=1);

namespace AsaasTools;

/** Validates tools/endpoint-map.json against the OpenAPI spec and the documentation index. */
final class MapValidator
{
    // Sanity counts: update together with the map when the official API grows.
    public const EXPECTED_OPERATIONS = 213;

    public const EXPECTED_SERVICES = 34;

    /** Models that exist hand-written in src/Models (after the 1.0.0 deletions). */
    public const HAND_WRITTEN_MODELS = [
        'AccountDocument', 'AccountDocumentList', 'AccountInfo', 'Anticipation', 'AnticipationList', 'Bill', 'BillList',
        'Chargeback', 'ChargebackList', 'Checkout', 'CreditBureauReport', 'CreditBureauReportList', 'CreditCard', 'Customer',
        'CustomerDeleteResult', 'CustomerList', 'EscrowAccount', 'Finance', 'FinancialTransaction', 'FinancialTransactionList',
        'FiscalInfo', 'Installment', 'InstallmentList', 'Invoice', 'InvoiceList', 'MobilePhoneRecharge', 'MobilePhoneRechargeList',
        'Notification', 'NotificationList', 'Payment', 'PaymentDeleteResult', 'PaymentDocument', 'PaymentDocumentList',
        'PaymentDunning', 'PaymentDunningList', 'PaymentLink', 'PaymentLinkList', 'PaymentList', 'PaymentRefund',
        'PaymentRefundList', 'PaymentSplit', 'PaymentSplitList', 'PaymentWithSummary', 'PaymentWithSummaryList',
        'PixTransaction', 'PixTransactionList', 'RecurringPix', 'RecurringPixList', 'Subaccount', 'SubaccountList',
        'Subscription', 'SubscriptionList', 'Transfer', 'TransferList', 'Webhook', 'WebhookList',
        'GenericResource', 'GenericList', 'DeleteResult',
    ];

    /** Models emitted by the generator: item models; the matching "<Name>List" is emitted with each. */
    public const GENERATED_MODELS = [
        'PaymentLinkImage', 'SubaccountAccessToken', 'PixAddressKey', 'PixAutomaticAuthorization',
        'PixAutomaticPaymentInstruction', 'RecurringPixItem',
    ];

    private const REQUIRED_KEYS = [
        'method', 'path', 'category', 'operationId', 'docSlug', 'service', 'property', 'sdkMethod', 'pathParams',
        'hasQuery', 'paginated', 'hasBody', 'multipart', 'requestClass', 'responseModel', 'status', 'notes',
    ];

    /** @return list<string> */
    public static function generatedModelNames(): array
    {
        $names = [];
        foreach (self::GENERATED_MODELS as $name) {
            array_push($names, $name, $name.'List');
        }

        return $names;
    }

    /**
     * @param  list<array<string, mixed>>  $map
     * @param  array<string, string>  $docPages  "METHOD path" => page slug (tools/doc-pages.json)
     * @return list<string>
     */
    public static function validate(array $map, Spec $spec, array $docPages): array
    {
        $errors = [];
        $seen = [];
        $pairs = [];
        $serviceProp = [];
        $serviceCategory = [];
        foreach ($map as $i => $entry) {
            $missing = array_diff(self::REQUIRED_KEYS, array_keys($entry));
            if ($missing !== []) {
                $errors[] = "entry #$i missing keys: ".implode(',', $missing);

                continue;
            }
            $key = $entry['method'].' '.$entry['path'];
            if (isset($seen[$key])) {
                $errors[] = "duplicate op $key";
            }
            $seen[$key] = true;
            $pairKey = $entry['service'].'.'.$entry['sdkMethod'];
            if (isset($pairs[$pairKey])) {
                $errors[] = "duplicate sdkMethod $pairKey";
            }
            $pairs[$pairKey] = true;
            if (($serviceProp[$entry['service']] ??= $entry['property']) !== $entry['property']) {
                $errors[] = "service {$entry['service']} has several properties";
            }
            if (($serviceCategory[$entry['service']] ??= $entry['category']) !== $entry['category']) {
                $errors[] = "service {$entry['service']} has several categories";
            }
            array_push($errors, ...self::validateEntry($entry, $key, $spec));
            if (($docPages[$key] ?? null) !== $entry['docSlug']) {
                $errors[] = "docSlug {$entry['docSlug']} is not the doc page of $key (".($docPages[$key] ?? 'no page').')';
            }
        }

        $specOps = $spec->operationKeys();
        $mapOps = array_keys($seen);
        if (count($map) !== self::EXPECTED_OPERATIONS) {
            $errors[] = 'expected '.self::EXPECTED_OPERATIONS.' entries, got '.count($map);
        }
        foreach (array_diff($specOps, $mapOps) as $op) {
            $errors[] = "in spec, not in map: $op";
        }
        foreach (array_diff($mapOps, $specOps) as $op) {
            $errors[] = "in map, not in spec: $op";
        }
        if (count($serviceProp) !== self::EXPECTED_SERVICES) {
            $errors[] = 'expected '.self::EXPECTED_SERVICES.' services, got '.count($serviceProp);
        }
        if (count(array_unique($serviceProp)) !== count($serviceProp)) {
            $errors[] = 'two services share a property';
        }

        return array_merge($errors, self::validateModels($map));
    }

    /** @param array<string, mixed> $entry @return list<string> */
    private static function validateEntry(array $entry, string $key, Spec $spec): array
    {
        $op = $spec->op($entry['method'], $entry['path']);
        if ($op === null) {
            return [];
        }
        $errors = [];
        $declared = $spec->pathParams($op);
        if ($declared !== $entry['pathParams'] || preg_match_all('/\{(\w+)\}/', $entry['path'], $m) === false || $m[1] !== $declared) {
            $errors[] = "pathParams differ from spec/path: $key";
        }
        [$type] = $spec->body($op);
        if ($entry['multipart'] !== ($type === 'multipart/form-data')) {
            $errors[] = "multipart flag differs from spec: $key";
        }
        if ($entry['hasQuery'] !== ($spec->queryParams($op) !== [])) {
            $errors[] = "hasQuery differs from spec: $key";
        }
        if ($entry['paginated'] !== $spec->isPaginated($op)) {
            $errors[] = "paginated flag differs from the response schema: $key";
        }
        if ($entry['hasBody'] && $entry['hasQuery']) {
            $errors[] = "body and query together are not supported: $key";
        }
        if (($entry['requestClass'] !== null) !== ($entry['hasBody'] || $entry['hasQuery'])) {
            $errors[] = "requestClass must exist exactly when hasBody or hasQuery: $key";
        }
        if (! preg_match('/^[A-Z]\w*\\\\[A-Z]\w*Request$/', (string) $entry['requestClass']) && $entry['requestClass'] !== null) {
            $errors[] = "bad requestClass name {$entry['requestClass']}: $key";
        }
        $content = $spec->successContent($op);
        $isPdf = isset($content['application/pdf']);
        if ($entry['responseModel'] === 'void' && $content !== []) {
            $errors[] = "void but response has content: $key";
        }
        if ($entry['responseModel'] === 'string' && ! $isPdf) {
            $errors[] = "string but not pdf: $key";
        }
        if ($isPdf && $entry['responseModel'] !== 'string') {
            $errors[] = "pdf response must map to string: $key";
        }

        return $errors;
    }

    /** @param list<array<string, mixed>> $map @return list<string> */
    private static function validateModels(array $map): array
    {
        $allowed = array_merge(self::HAND_WRITTEN_MODELS, self::generatedModelNames(), ['string', 'void']);
        $used = array_unique(array_column($map, 'responseModel'));
        $errors = [];
        foreach (array_diff($used, $allowed) as $model) {
            $errors[] = "unknown model $model";
        }
        foreach (array_diff(self::generatedModelNames(), $used) as $model) {
            $errors[] = "generated model unused: $model";
        }

        return $errors;
    }
}
