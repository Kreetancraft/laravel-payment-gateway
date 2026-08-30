<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Gateways;

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
        $orderNo = (string) ($data['order_no'] ?? $data['transaction_id'] ?? '');

        try {
            $res = $this->client->transactionList(['orderNo' => [$orderNo]]);
            $status = (string) data_get($res, 'response.Data.0.transactionStatus', 'PENDING');
            $amount = (float) data_get($res, 'response.Data.0.amount', 0);

            return VerificationResult::success(
                transactionId: $orderNo,
                status: strtolower($status),
                amount: $amount,
                currency: $this->resolveCurrency($data['currency'] ?? null)
            );
        } catch (Throwable $e) {
            return VerificationResult::failure($orderNo, $e->getMessage());
        }
    }

    public function webhook(array $payload): WebhookResult
    {
        $orderNo = (string) (data_get($payload, 'orderNo') ?? data_get($payload, 'order_no') ?? '');

        if ($orderNo === '') {
            return WebhookResult::failure('payment.failed', '', 'Missing orderNo in webhook payload.');
        }

        $result = $this->verify(['order_no' => $orderNo]);

        return $result->success
            ? WebhookResult::success('payment.completed', $orderNo, $result->status, $result->amount, $result->currency)
            : WebhookResult::failure('payment.failed', $orderNo, $result->errorMessage ?? 'Verification failed.');
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

        return Str::upper($seed.'-'.base_convert((string) $ms, 10, 36));
    }
}
