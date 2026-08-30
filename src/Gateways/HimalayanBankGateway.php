<?php

declare(strict_types=1);

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

        try {
            $confirmationUrl = $this->resolveRedirectUrl('success', ['order' => $orderNo, 'reference' => $orderNo], $data['return_url'] ?? null);
            $failedUrl = $this->resolveRedirectUrl('failed', ['order' => $orderNo, 'reference' => $orderNo]);
            $cancelUrl = $this->resolveRedirectUrl('cancel', ['order' => $orderNo, 'reference' => $orderNo]);
            $backendUrl = $this->resolveWebhookUrl();

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
                'request3dsFlag' => ($data['request_3ds'] ?? true) ? 'Y' : 'N',
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

    public function refund(string $transactionId, float $amount): RefundResult
    {
        try {
            $this->client->void([
                'officeId' => $this->gateway->getHimalayanOfficeId(),
                'orderNo' => $transactionId,
                'productDescription' => "Refund {$transactionId}",
                'issuerApprovalCode' => '000000',
                'actionBy' => 'System',
                'voidAmount' => [
                    'amountText' => str_pad((string) (int) round($amount * 100), 12, '0', STR_PAD_LEFT),
                    'currencyCode' => $this->gateway->getConfigValue('currency', 'NPR'),
                    'decimalPlaces' => 2,
                    'amount' => $amount,
                ],
            ]);

            return RefundResult::success($transactionId, $amount);
        } catch (Throwable $e) {
            return RefundResult::failure($transactionId, $amount, $e->getMessage());
        }
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
                return VerificationResult::failure($orderNo, 'No transaction records found for this order on gateway.');
            }

            $rawStatus = strtoupper((string) data_get($tx, 'transactionStatus', 'PENDING'));
            $amount = (float) data_get($tx, 'amount', 0);
            $currency = $this->resolveCurrency($data['currency'] ?? (string) data_get($tx, 'currencyCode'));
            $paidAt = (string) data_get($tx, 'transactionDateTime', now()->toIso8601String());

            // Check if status represents a successful charge
            $isSuccessful = in_array($rawStatus, ['0000', 'COMPLETED', 'SETTLED', 'SUCCESS', 'APPROVED', 'PAID'], true);

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

            $isCancelled = in_array($rawStatus, ['CANCELLED', 'CANCELED', 'USER_CANCELLED'], true);
            $statusNormalized = $isCancelled ? 'cancelled' : 'failed';

            return new VerificationResult(
                success: false,
                transactionId: $orderNo,
                status: $statusNormalized,
                amount: $amount,
                currency: $currency,
                errorMessage: "Transaction {$statusNormalized} (Gateway Status: {$rawStatus})",
                reference: $orderNo,
            );
        } catch (Throwable $e) {
            return VerificationResult::failure($orderNo, $e->getMessage(), reference: $orderNo);
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
        $ms = (int) round(microtime(true) * 1000);
        $suffix = base_convert((string) $ms, 10, 36);

        return Str::upper("{$seed}-{$suffix}");
    }

    private function resolveRedirectUrl(string $type, array $params = [], ?string $overrideUrl = null): string
    {
        $query = http_build_query($params);

        if (filled($overrideUrl)) {
            $separator = str_contains($overrideUrl, '?') ? '&' : '?';

            return "{$overrideUrl}{$separator}{$query}";
        }

        $configUrl = config("payment-gateway.routes.redirect_urls.{$type}");
        if (filled($configUrl)) {
            if (filter_var($configUrl, FILTER_VALIDATE_URL)) {
                $separator = str_contains((string) $configUrl, '?') ? '&' : '?';

                return "{$configUrl}{$separator}{$query}";
            }
            if (Route::has((string) $configUrl)) {
                return route((string) $configUrl, $params);
            }
        }

        $routeName = config("payment-gateway.routes.names.{$type}", "payment.{$type}");
        if (Route::has($routeName)) {
            return route($routeName, $params);
        }

        return url("/payment/{$type}?{$query}");
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

        return url('/payment/webhook/himalayan');
    }
}
