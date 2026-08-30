<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Gateways;

use Illuminate\Support\Str;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Data\WebhookResult;
use Kreetancraft\PaymentGateway\Models\Gateway;

class HimalayanBankGateway extends AbstractGateway
{
    public function charge(array $data): PaymentResult
    {
        $client = app(\Kreetancraft\PaymentGateway\Support\HblClient::class);
        $orderNo = $this->generateOrderNo($data['reference_seed'] ?? Str::random(8));
        $currency = $this->resolveCurrency($data['currency'] ?? 'NPR');

        $response = $this->gateway->getClient()->prePaymentUi([
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
                'confirmationURL' => route('payment.success', ['order' => $orderNo]),
                'failedURL' => route('payment.cancel', ['order' => $orderNo]),
                'cancellationURL' => route('payment.cancel', ['order' => $orderNo]),
                'backendURL' => route('payment.webhook', ['gateway' => 'himalayan']),
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
            return PaymentResult::failure($orderNo, 'HBL did not return payment URL');
        }

        return PaymentResult::success($orderNo, $url);
    }

    public function refund(string $transactionId, float $amount): RefundResult
    {
        $client = app(\Kreetancraft\PaymentGateway\Support\HblClient::class);
        try {
            $client->void([
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
        } catch (\Throwable $e) {
            return RefundResult::failure($transactionId, $amount, $e->getMessage());
        }
    }

    public function verify(array $data): VerificationResult
    {
        $client = app(\Kreetancraft\PaymentGateway\Support\HblClient::class);
        $orderNo = $data['order_no'] ?? $data['transaction_id'] ?? '';
        try {
            $res = $client->transactionList(['orderNo' => [$orderNo]]);
            $status = data_get($res, 'response.Data.0.transactionStatus', 'PENDING');
            $amount = (float) data_get($res, 'response.Data.0.amount', 0);
            return VerificationResult::success($orderNo, strtolower($status), $amount, $this->resolveCurrency($data['currency'] ?? null));
        } catch (\Throwable $e) {
            return VerificationResult::failure($orderNo, $e->getMessage());
        }
    }

    public function webhook(array $payload): WebhookResult
    {
        $orderNo = (string) (data_get($payload, 'orderNo') ?? data_get($payload, 'order_no') ?? '');
        if ($orderNo === '') {
            return WebhookResult::failure('payment.failed', '', 'Missing orderNo');
        }
        $result = $this->verify(['order_no' => $orderNo]);
        return $result->success
            ? WebhookResult::success('payment.completed', $orderNo, $result->status, $result->amount, $result->currency)
            : WebhookResult::failure('payment.failed', $orderNo, $result->errorMessage ?? 'Failed');
    }

    public function getCode(): string { return 'himalayan'; }
    public function getLabel(): string { return 'Himalayan Bank'; }
    public function getIcon(): string { return $this->gateway->getIcon(); }
    public function checkoutRedirect(): bool { return true; }

    private function resolveCurrency(?string $requested): string
    {
        $requested = $requested ? strtoupper($requested) : null;
        $supported = $this->getSupportedCurrencies();
        if ($requested && in_array($requested, $supported, true)) {
            return $requested;
        }
        return strtoupper((string) $this->getConfigValue('currency', 'NPR'));
    }

    public function getSupportedCurrencies(): array
    {
        return $this->gateway->getSupportedCurrencies();
    }

    private function generateOrderNo(string $seed): string
    {
        $ms = (int) round(microtime(true) * 1000);

        return Str::upper($seed.'-'.base_convert((string) $ms, 10, 36));
    }
}