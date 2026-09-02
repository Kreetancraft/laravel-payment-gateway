<?php

namespace Kreetancraft\PaymentGateway\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Data\WebhookResult;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Support\HblClient;
use RuntimeException;
use Throwable;

class HimalayanBankGateway extends AbstractGateway
{
    public function __construct(
        Gateway $gateway,
        private readonly HblClient $client,
    ) {
        parent::__construct($gateway);
    }

    public function charge(array $data): PaymentResult
    {
        $orderNo = $this->generateOrderNo($data['reference_seed'] ?? Str::random(8));
        $currency = $this->resolveCurrency($data['currency'] ?? 'NPR');
        // Respect gateway's 3DS toggle (WP parity: Enable/Disable 3D Secure), allow per-request override
        // Not overridable from the request. `POST /payment/checkout` is public,
        // and the whole body used to be spread into this method — so anyone
        // could send `request_3ds=false` and turn off 3-D Secure for a payment,
        // moving chargeback liability onto the merchant. Whether to ask for 3DS
        // is the merchant's decision, and it lives on the gateway.
        $request3ds = $this->gateway->getHimalayanRequest3ds();

        try {
            $confirmationUrl = $this->resolveRedirectUrl('success', ['order' => $orderNo, 'reference' => $orderNo, 'orderNo' => $orderNo], $data['return_url'] ?? null);
            $failedUrl = $this->resolveRedirectUrl('failed', ['order' => $orderNo, 'reference' => $orderNo, 'orderNo' => $orderNo]);
            $cancelUrl = $this->resolveRedirectUrl('cancel', ['order' => $orderNo, 'reference' => $orderNo, 'orderNo' => $orderNo]);
            $backendUrl = $this->resolveWebhookUrl();

            $purchaseItems = $data['purchase_items'] ?? $data['purchaseItems'] ?? $this->buildDefaultPurchaseItems($orderNo, $currency, (int) ($data['amount_cents'] ?? 0));
            $customFields = $data['custom_fields'] ?? $data['customFieldList'] ?? [['fieldName' => 'TestField', 'fieldValue' => 'This is test']];

            $response = $this->client->prePaymentUi([
                'apiRequest' => [
                    'requestMessageID' => (string) Str::uuid(),
                    'requestDateTime' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                    'language' => 'en-US',
                ],
                'officeId' => $this->gateway->getHimalayanOfficeId(),
                'orderNo' => $orderNo,
                'productDescription' => $data['description'] ?? "Payment {$orderNo}",
                'paymentType' => 'CC',
                'paymentCategory' => 'ECOM',
                'storeCardDetails' => ['storeCardFlag' => 'N', 'storedCardUniqueID' => null],
                'installmentPaymentDetails' => ['ippFlag' => 'N', 'installmentPeriod' => 0, 'interestType' => null],
                'mcpFlag' => 'N',
                'request3dsFlag' => $request3ds ? 'Y' : 'N',
                'transactionAmount' => [
                    'amountText' => str_pad((string) $data['amount_cents'], 12, '0', STR_PAD_LEFT),
                    'currencyCode' => $currency,
                    'decimalPlaces' => 2,
                    'amount' => round($data['amount_cents'] / 100, 2),
                ],
                'notificationURLs' => [
                    'confirmationURL' => $confirmationUrl,
                    'failedURL' => $failedUrl,
                    'cancellationURL' => $cancelUrl,
                    'backendURL' => $backendUrl,
                ],
                'deviceDetails' => [
                    'browserIp' => request()->ip() ?? '0.0.0.0',
                    'browser' => (string) request()->userAgent(),
                    'browserUserAgent' => (string) request()->userAgent(),
                    'mobileDeviceFlag' => 'N',
                ],
                'purchaseItems' => $purchaseItems,
                'customFieldList' => $customFields,
            ]);

            $url = data_get($response, 'response.Data.paymentPage.paymentPageURL');

            if (blank($url)) {
                return PaymentResult::failure($orderNo, 'HBL did not return a valid payment URL.');
            }

            return PaymentResult::success($orderNo, (string) $url);
        } catch (Throwable $e) {
            return PaymentResult::failure($orderNo, $e->getMessage());
        }
    }

    /**
     * Reverse a payment.
     *
     * Void before settlement, Refund after. PACO will not void a settled
     * transaction, so choosing by the transaction's own state is the difference
     * between a refund happening and a refund being reported as happening.
     *
     * Three things were wrong here and each one reported success regardless:
     * the response was never inspected, so a PACO-level rejection returned
     * success; `issuerApprovalCode` was hardcoded `'000000'` where the demo notes
     * it must be the approval code from the original payment; and the currency
     * came from a `currency` credential that does not exist, so every reversal
     * was denominated NPR whatever the payment was in.
     */
    public function refund(string $transactionId, float $amount): RefundResult
    {
        try {
            $tx = data_get($this->client->transactionList(['orderNo' => [$transactionId]]), 'response.Data.0');

            if (! $tx) {
                return RefundResult::failure($transactionId, $amount, 'No transaction record on the gateway to reverse.');
            }

            $currency = $this->resolveCurrency((string) data_get($tx, 'currencyCode'));
            $approvalCode = (string) (data_get($tx, 'approvalCode') ?? data_get($tx, 'PaymentStatusInfo.ApprovalCode') ?? '');

            $amountBlock = [
                'amountText' => str_pad((string) (int) round($amount * 100), 12, '0', STR_PAD_LEFT),
                'currencyCode' => $currency,
                'decimalPlaces' => 2,
                'amount' => $amount,
            ];

            $common = [
                'officeId' => $this->gateway->getHimalayanOfficeId(),
                'orderNo' => $transactionId,
                'productDescription' => "Refund {$transactionId}",
                'actionBy' => 'System',
            ];

            if ($this->isSettled($tx)) {
                $response = $this->client->refund($common + [
                    'issuerApprovalCode' => $approvalCode,
                    'refundAmount' => $amountBlock,
                ]);
            } else {
                $response = $this->client->void($common + [
                    'issuerApprovalCode' => $approvalCode,
                    'voidAmount' => $amountBlock,
                ]);
            }

            $responseCode = (string) (data_get($response, 'response.ResponseCode') ?? data_get($response, 'response.responseCode') ?? '');
            $message = (string) (
                data_get($response, 'response.ErrorDetails.Message')
                ?? data_get($response, 'response.ResponseMessage')
                ?? data_get($response, 'response.message')
                ?? ''
            );

            // PACO signals success with 0000. Anything else, including a 200 with
            // an error envelope, is a refusal.
            if ($responseCode !== '' && $responseCode !== '0000') {
                return RefundResult::failure(
                    $transactionId,
                    $amount,
                    $message !== '' ? $message : "Gateway refused the reversal (code {$responseCode})."
                );
            }

            return RefundResult::success($transactionId, $amount);
        } catch (Throwable $e) {
            return RefundResult::failure($transactionId, $amount, $e->getMessage());
        }
    }

    /**
     * Whether PACO has settled this transaction, which decides Void vs Refund.
     *
     * `A` is authorised and not yet settled; `S` is settled.
     *
     * @param  array<string, mixed>|object  $tx
     */
    private function isSettled(mixed $tx): bool
    {
        $status = strtoupper(trim((string) (
            data_get($tx, 'PaymentStatusInfo.PaymentStatus')
            ?? data_get($tx, 'paymentStatus')
            ?? data_get($tx, 'transactionStatus')
            ?? ''
        )));

        return in_array($status, ['S', 'SETTLED'], true);
    }

    public function verify(array $data): VerificationResult
    {
        $orderNo = (string) (
            $data['order_no']
            ?? $data['orderNo']
            ?? $data['order']
            ?? $data['reference']
            ?? $data['transaction_id']
            ?? ''
        );

        if ($orderNo === '') {
            return VerificationResult::failure('', 'Missing order number for verification.');
        }

        try {
            $res = $this->client->transactionList(['orderNo' => [$orderNo]]);
            $tx = data_get($res, 'response.Data.0');

            if (! $tx) {
                // PACO has no record yet. On a fast return from the payment page
                // that is normal, not a failure.
                return VerificationResult::undetermined($orderNo, 'No transaction record on the gateway yet.', reference: $orderNo);
            }

            // PaymentStatusInfo.PaymentStatus first, then the older field names.
            // PACO puts the authoritative value in the nested object; reading only
            // `transactionStatus` misses it on current responses.
            $rawStatus = strtoupper(trim((string) (
                data_get($tx, 'PaymentStatusInfo.PaymentStatus')
                ?? data_get($tx, 'paymentStatus')
                ?? data_get($tx, 'transactionStatus')
                ?? data_get($tx, 'status')
                ?? ''
            )));
            $amount = (float) data_get($tx, 'amount', 0);
            $currency = $this->resolveCurrency($data['currency'] ?? (string) data_get($tx, 'currencyCode'));
            $paidAt = (string) data_get($tx, 'transactionDateTime', now()->toIso8601String());

            // PACO answers in single letters: A is authorised pre-settlement, S is
            // settled. Neither was in the list this shipped with — which was guessed,
            // since the vendor demo parses no statuses at all — so every genuinely
            // paid transaction read as not-successful. These lists come from the
            // monolith, whose tests assert them against captured PACO payloads.
            $paidStatuses = array_map(
                'strtoupper',
                (array) config('payment-gateway.gateways.himalayan.paid_statuses', [
                    'A', 'S', 'Settled', 'Success', 'Successful', 'Completed', 'Paid', 'Approved', '0000',
                ])
            );

            $failedStatuses = array_map(
                'strtoupper',
                (array) config('payment-gateway.gateways.himalayan.failed_statuses', [
                    'F', 'V', 'C', 'Failed', 'Voided', 'Cancelled', 'Canceled', 'Declined', 'Rejected', 'Expired',
                ])
            );

            $isSuccessful = in_array($rawStatus, $paidStatuses, true);

            if ($isSuccessful) {
                return VerificationResult::success(
                    transactionId: $orderNo,
                    status: 'completed',
                    amount: $amount,
                    currency: $currency,
                    paidAt: $paidAt,
                    reference: $orderNo,
                );
            }

            // Anything in neither list is still in flight — 3DS in progress, or an
            // authorisation the bank has not settled. Reporting that as failed is
            // what made the buyer's return from the payment page, the moment a
            // pending state is most likely, write the payment off.
            if (! in_array($rawStatus, $failedStatuses, true)) {
                return VerificationResult::undetermined(
                    $orderNo,
                    "Transaction not settled yet (gateway status: {$rawStatus}).",
                    reference: $orderNo,
                );
            }

            $isCancelled = in_array($rawStatus, ['C', 'CANCELLED', 'CANCELED', 'USER_CANCELLED'], true);
            $statusNormalized = $isCancelled ? 'cancelled' : 'failed';

            // "Gateway Status: F" on its own is not something anyone can act on.
            // PACO carries the useful detail in other fields, and which of them
            // are filled says where it went wrong: a null PspReferenceNo with no
            // approval code means it never reached the card acquirer at all, so
            // the problem is the request or the merchant profile rather than the
            // buyer's card.
            $detail = array_filter([
                'step' => data_get($tx, 'PaymentStatusInfo.PaymentStep'),
                'reason' => data_get($tx, 'FailedReason'),
                'psp' => data_get($tx, 'PspResponse'),
                'auth' => data_get($tx, 'AuthenticationStatus'),
                'acquirer' => data_get($tx, 'PspReferenceNo') ? 'reached' : 'not reached',
            ], fn ($value): bool => filled($value));

            $detailText = implode(', ', array_map(
                fn (string $key, mixed $value): string => "{$key}: {$value}",
                array_keys($detail),
                $detail,
            ));

            return new VerificationResult(
                success: false,
                transactionId: $orderNo,
                status: $statusNormalized,
                amount: $amount,
                currency: $currency,
                errorMessage: "Transaction {$statusNormalized} (gateway status: {$rawStatus}"
                    .($detailText !== '' ? "; {$detailText}" : '').')',
                reference: $orderNo,
            );
        } catch (Throwable $e) {
            // Could not ask the bank. Not the same as the bank saying no.
            return VerificationResult::undetermined($orderNo, $e->getMessage(), reference: $orderNo);
        }
    }

    public function webhook(Request $request): WebhookResult
    {
        $payload = $request->all();

        $orderNo = (string) (
            data_get($payload, 'orderNo')
            ?? data_get($payload, 'order_no')
            ?? data_get($payload, 'order')
            ?? ''
        );

        if ($orderNo === '') {
            return WebhookResult::failure('payment.failed', '', 'Missing orderNo in webhook payload.');
        }

        $result = $this->verify(['order_no' => $orderNo]);

        if (! $result->success) {
            return WebhookResult::failure('payment.failed', $orderNo, $result->errorMessage ?? 'Verification failed.');
        }

        return WebhookResult::success('payment.completed', $orderNo, $result->status, $result->amount, $result->currency);
    }

    public function getCode(): string
    {
        return 'himalayan';
    }

    public function getLabel(): string
    {
        return 'Himalayan Bank (2C2P PACO)';
    }

    public function getIcon(): string
    {
        return $this->gateway->getIcon() ?: 'https://www.himalayanbank.com/themes/himalayan/assets/ico/hbl-icon.png';
    }

    public function checkoutRedirect(): bool
    {
        return true;
    }

    public function getSupportedCurrencies(): array
    {
        return $this->gateway->getSupportedCurrencies();
    }

    private function resolveCurrency(?string $requested): string
    {
        $requested = $requested ? strtoupper($requested) : null;
        $supported = $this->getSupportedCurrencies();

        if ($requested && in_array($requested, $supported, true)) {
            return $requested;
        }

        return strtoupper((string) $this->gateway->getConfigValue('currency', 'NPR'));
    }

    private function generateOrderNo(string $seed): string
    {
        // Numeric ms timestamp like demo getPreciseTimestamp(3) for 2C2P compatibility
        // Keep seed prefix only if explicitly numeric-friendly; otherwise use pure timestamp
        if (filled($seed) && preg_match('/^[A-Z0-9\-_]+$/i', $seed) && strlen($seed) <= 8) {
            $ms = (int) round(microtime(true) * 1000);

            return (string) $ms;
        }

        return (string) (int) round(microtime(true) * 1000);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * The line item PACO wants alongside the transaction.
     *
     * Three things here came straight from the vendor's demo and were wrong for
     * anybody else: the currency was hardcoded NPR while the method was handed
     * the real one and ignored it — so a USD charge shipped a USD total beside a
     * rupee line item — and the reference and sub-merchant were the demo's own
     * literal values, including the word "string".
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildDefaultPurchaseItems(string $orderNo, string $currency, int $amountCents): array
    {
        return [
            [
                'purchaseItemType' => 'ticket',
                'referenceNo' => $orderNo,
                'purchaseItemDescription' => "Payment for {$orderNo}",
                'purchaseItemPrice' => [
                    'amountText' => str_pad((string) $amountCents, 12, '0', STR_PAD_LEFT),
                    'currencyCode' => $currency,
                    'decimalPlaces' => 2,
                    'amount' => round($amountCents / 100, 2),
                ],
                'subMerchantID' => (string) $this->gateway->getHimalayanOfficeId(),
                'passengerSeqNo' => 1,
            ],
        ];
    }

    private function resolveWebhookUrl(): string
    {
        $configUrl = config('payment-gateway.routes.redirect_urls.webhook');
        if (filled($configUrl) && filter_var($configUrl, FILTER_VALIDATE_URL)) {
            return (string) $configUrl;
        }

        if (Route::has('payment.webhook')) {
            return route('payment.webhook', ['gateway' => 'himalayan']);
        }

        // Do not guess. That route only exists under the API prefix, so with
        // PAYMENT_GATEWAY_REGISTER_API=false the guessed URL 404s — and the bank's
        // notification disappears silently, hours later, in production. Fail here
        // instead, where the message can name the setting to change.
        throw new RuntimeException(
            'No webhook route is registered, so there is no URL to give the bank. '
            .'Set payment-gateway.routes.redirect_urls.webhook to the URL it should call, '
            .'or enable the package API routes.'
        );
    }
}
