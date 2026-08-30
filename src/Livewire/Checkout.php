<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Models\Coupon;

class Checkout extends Component
{
    #[Url]
    public ?string $gateway = null;

    #[Url]
    public ?int $amount = null;

    #[Url]
    public ?string $currency = null;

    #[Url]
    public ?string $coupon = null;

    public string $customerEmail = '';

    public string $customerName = '';

    public string $customerPhone = '';

    public string $amountInput = '';

    public string $currencyInput = 'USD';

    public string $selectedGateway = '';

    public string $couponCode = '';

    public ?string $errorMessage = null;

    public bool $isRedirecting = false;

    public ?string $appliedCouponCode = null;

    public int $appliedDiscountCents = 0;

    public bool $hasFreeShipping = false;

    public function mount(?string $gateway = null): void
    {
        $this->loadInitialValues($gateway);
        $this->selectGatewayIfOnlyOne();
        $this->fillAmountFromUrl();
        $this->applyCouponFromUrl();
        $this->autoChargeIfSingleGateway();
    }

    private function loadInitialValues(?string $gateway): void
    {
        $this->couponCode = request()->query('coupon', '');
        $this->gateway = $gateway;

        $enabled = $this->getEnabledGatewayCodes();

        if (empty($enabled)) {
            abort(404, 'No payment gateways are enabled.');
        }

        if (filled($gateway)) {
            $this->selectedGateway = $gateway;
        }
    }

    private function selectGatewayIfOnlyOne(): void
    {
        $enabled = $this->getEnabledGatewayCodes();

        if (blank($this->selectedGateway) && count($enabled) === 1) {
            $this->selectedGateway = $enabled[0];
            $this->gateway = $enabled[0];
        }

        if (filled($this->gateway) && blank($this->selectedGateway)) {
            $this->selectedGateway = $this->gateway;
        }
    }

    private function fillAmountFromUrl(): void
    {
        if ($this->amount !== null) {
            $this->amountInput = number_format($this->amount / 100, 2, '.', '');
        }

        if (filled($this->currency)) {
            $this->currencyInput = strtoupper($this->currency);
        }
    }

    private function applyCouponFromUrl(): void
    {
        if ($this->coupon) {
            $this->couponCode = $this->coupon;
            $this->applyCoupon();
        }
    }

    private function autoChargeIfSingleGateway(): void
    {
        $enabled = $this->getEnabledGatewayCodes();

        if (count($enabled) === 1 && $this->hasAmountAndCurrency()) {
            $this->chargeCustomer();
        }
    }

    private function hasAmountAndCurrency(): bool
    {
        return $this->amount !== null && $this->amount > 0 && filled($this->currency);
    }

    private function getEnabledGatewayCodes(): array
    {
        return app(GatewayResolver::class)->getEnabledGateways();
    }

    #[Computed]
    public function enabledGateways(): array
    {
        $resolver = app(GatewayResolver::class);
        $codes = $resolver->getEnabledGateways();

        return collect($codes)->map(function (string $code) use ($resolver): array {
            $config = $resolver->getGatewayConfig($code);

            if ($config === null) {
                return ['code' => $code, 'label' => $code, 'icon' => '', 'currencies' => []];
            }

            return [
                'code' => $config->getCode(),
                'label' => $config->getLabel(),
                'icon' => $config->getIcon(),
                'currencies' => $config->getSupportedCurrencies(),
                'checkout_redirect' => $config->checkoutRedirect(),
            ];
        })->all();
    }

    #[Computed]
    public function isSingleGateway(): bool
    {
        return count($this->enabledGateways) === 1;
    }

    public function updatedSelectedGateway(string $value): void
    {
        $this->gateway = $value;
        $this->errorMessage = null;
    }

    public function applyCoupon(): void
    {
        $this->clearError();

        if ($this->isCouponCodeEmpty()) {
            $this->showError('Please enter a coupon code.');
            return;
        }

        $coupon = $this->findCouponByCode($this->couponCode);

        if (! $this->isCouponValid($coupon)) {
            $this->showError('Invalid or expired coupon code.');
            return;
        }

        $amountCents = $this->getAmountInCents();

        if (! $this->isValidAmount($amountCents)) {
            $this->showError('Please enter a valid amount.');
            return;
        }

        $currency = $this->getCurrencyCode();

        if (! $coupon->canApply(auth()->id(), $amountCents, $currency)) {
            $this->showError('This coupon cannot be applied to your order.');
            return;
        }

        $discount = $coupon->calculateDiscount($amountCents);

        if ($discount <= 0) {
            $this->showError('This coupon does not apply to your order amount.');
            return;
        }

        $this->saveAppliedCoupon($coupon, $discount);
    }

    private function isCouponCodeEmpty(): bool
    {
        return blank($this->couponCode);
    }

    private function findCouponByCode(string $code): ?Coupon
    {
        return Coupon::where('code', $code)->first();
    }

    private function isCouponValid(?Coupon $coupon): bool
    {
        if (! $coupon) {
            return false;
        }

        return $coupon->isValid(auth()->id(), $this->amount, $this->currencyInput);
    }

    private function isValidAmount(?int $cents): bool
    {
        return $cents !== null && $cents > 0;
    }

    private function getCurrencyCode(): string
    {
        return strtoupper(trim($this->currencyInput ?: $this->currency ?? 'USD'));
    }

    private function saveAppliedCoupon(Coupon $coupon, int $discount): void
    {
        $this->appliedCouponCode = $coupon->code;
        $this->appliedDiscountCents = $discount;
        $this->hasFreeShipping = $coupon->is_free_shipping;
        $this->couponCode = '';
        session()->flash('coupon_message', "Coupon {$coupon->code} applied! Discount: " . number_format($discount / 100, 2));
    }

    public function removeCoupon(): void
    {
        $this->appliedCouponCode = null;
        $this->appliedDiscountCents = 0;
        $this->hasFreeShipping = false;
    }

    #[Computed]
    public function finalAmountCents(): int
    {
        return max(0, $this->getAmountInCents() - $this->appliedDiscountCents);
    }

    #[Computed]
    public function formattedAmount(): string
    {
        return number_format($this->finalAmountCents() / 100, 2);
    }

    #[Computed]
    public function formattedDiscount(): string
    {
        return number_format($this->appliedDiscountCents / 100, 2);
    }

    public function charge(): mixed
    {
        $this->clearError();
        $this->isRedirecting = false;

        if (! $this->hasSelectedGateway()) {
            $this->showError('Please select a payment gateway.');
            return null;
        }

        $amountCents = $this->getAmountInCents();

        if (! $this->isValidAmount($amountCents)) {
            $this->showError('Please enter a valid amount.');
            return null;
        }

        if (! $this->isValidCurrency()) {
            $this->showError('Currency must be a 3-letter code.');
            return null;
        }

        if (! $this->isValidEmail()) {
            $this->showError('Please enter a valid email address.');
            return null;
        }

        $result = $this->sendChargeRequest($amountCents);

        if (! $result->success) {
            $this->showError($result->errorMessage ?? 'Payment failed. Please try again.');
            return null;
        }

        return $this->handleSuccessfulCharge($result);
    }

    private function hasSelectedGateway(): bool
    {
        return filled($this->selectedGateway);
    }

    private function isValidCurrency(): bool
    {
        return strlen($this->getCurrencyCode()) === 3;
    }

    private function isValidEmail(): bool
    {
        if (blank($this->customerEmail)) {
            return true;
        }

        return filter_var($this->customerEmail, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function sendChargeRequest(int $amountCents): \Kreetancraft\PaymentGateway\Data\PaymentResult
    {
        $payload = $this->buildChargePayload($amountCents);

        return ChargePaymentAction::run($payload);
    }

    private function buildChargePayload(int $amountCents): array
    {
        return [
            'amount_cents' => $amountCents,
            'currency' => $this->getCurrencyCode(),
            'gateway' => $this->selectedGateway,
            'customer_email' => $this->customerEmail ?: null,
            'customer_name' => $this->customerName ?: null,
            'customer_phone' => $this->customerPhone ?: null,
            'description' => "Payment via {$this->selectedGateway}",
            'metadata' => [
                'source' => 'livewire_checkout',
                'original_amount_cents' => $this->getAmountInCents(),
                'discount_cents' => $this->appliedDiscountCents,
                'applied_coupon' => $this->appliedCouponCode,
                'has_free_shipping' => $this->hasFreeShipping,
            ],
        ];
    }

    private function handleSuccessfulCharge(\Kreetancraft\PaymentGateway\Data\PaymentResult $result): mixed
    {
        if (filled($result->redirectUrl)) {
            $this->isRedirecting = true;
            return $this->redirect($result->redirectUrl, navigate: false);
        }

        session()->flash('payment_success', $result->orderReference);

        $successRoute = config('payment-gateway.routes.names.success', 'payment.success');

        if (\Illuminate\Support\Facades\Route::has($successRoute)) {
            return $this->redirect(route($successRoute, ['reference' => $result->orderReference]), navigate: true);
        }

        return null;
    }

    private function getAmountInCents(): int
    {
        $cents = $this->resolveAmountCents();

        return $cents ?? 0;
    }

    private function resolveAmountCents(): ?int
    {
        if (filled($this->amountInput)) {
            $normalized = trim($this->amountInput);

            if (! is_numeric($normalized)) {
                return null;
            }

            return (int) round((float) $normalized * 100);
        }

        if ($this->amount !== null && $this->amount > 0) {
            return $this->amount;
        }

        return null;
    }

    private function clearError(): void
    {
        $this->errorMessage = null;
    }

    private function showError(string $message): void
    {
        $this->errorMessage = $message;
    }

    public function render(): View
    {
        return view('payment-gateway::livewire.checkout');
    }
}
