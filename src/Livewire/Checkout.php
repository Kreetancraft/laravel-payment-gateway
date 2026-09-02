<?php

namespace Kreetancraft\PaymentGateway\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class Checkout extends Component
{
    #[Url]
    public int $step = 1;

    #[Url]
    public ?string $gateway = null;

    #[Url]
    public int|float|string|null $amount = null;

    #[Url]
    public ?string $currency = null;

    #[Url]
    public ?string $coupon = null;

    public string $orderTitle = 'Order Payment';

    public ?string $description = '';

    public ?string $customerEmail = '';

    public ?string $customerName = '';

    public ?string $customerPhone = '';

    public ?string $amountInput = '';

    public ?string $currencyInput = 'USD';

    public ?string $selectedGateway = '';

    public ?string $couponCode = '';

    public ?string $returnUrl = null;

    /**
     * @var array<string, mixed>
     */
    public array $metadata = [];

    public ?string $errorMessage = null;

    public bool $isRedirecting = false;

    public ?string $appliedCouponCode = null;

    public int $appliedDiscountCents = 0;

    public bool $hasFreeShipping = false;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function mount(
        ?string $gateway = null,
        int|float|string|null $amount = null,
        ?string $currency = null,
        ?string $customerName = null,
        ?string $customerEmail = null,
        ?string $customerPhone = null,
        ?string $orderTitle = null,
        ?string $description = null,
        ?string $coupon = null,
        ?int $step = null,
        ?string $returnUrl = null,
        array $metadata = [],
    ): void {
        // Guard against Livewire passing null to typed string property
        $orderTitle = $orderTitle ?? '';

        $this->loadInitialValues(
            gateway: $gateway,
            amount: $amount,
            currency: $currency,
            customerName: $customerName,
            customerEmail: $customerEmail,
            customerPhone: $customerPhone,
            orderTitle: $orderTitle,
            description: $description,
            coupon: $coupon,
            step: $step,
            returnUrl: $returnUrl,
            metadata: $metadata,
        );

        $this->autoSelectGateway();
        $this->applyInitialCoupon();
        $this->determineStartingStep($step);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function loadInitialValues(
        ?string $gateway,
        int|float|string|null $amount,
        ?string $currency,
        ?string $customerName,
        ?string $customerEmail,
        ?string $customerPhone,
        ?string $orderTitle,
        ?string $description,
        ?string $coupon,
        ?int $step,
        ?string $returnUrl,
        array $metadata,
    ): void {
        $this->gateway = $gateway ?: request()->query('gateway', $this->gateway);
        $this->selectedGateway = (string) $this->gateway;

        // Amount parsing (handles cents integer, float string like "150.00", or URL param)
        $rawAmount = $amount ?? request()->query('amount', $this->amount);
        if ($rawAmount !== null && $rawAmount !== '') {
            if (is_numeric($rawAmount)) {
                $numeric = (float) $rawAmount;
                // If larger than 1000 and whole integer with no decimal, or explicit cents
                if (is_int($rawAmount) && $rawAmount >= 100 && ! str_contains((string) $rawAmount, '.')) {
                    $this->amountInput = number_format($rawAmount / 100, 2, '.', '');
                } else {
                    $this->amountInput = number_format($numeric, 2, '.', '');
                }
            }
        }

        // Currency
        $rawCurrency = $currency ?: request()->query('currency', $this->currency);
        if (filled($rawCurrency)) {
            $this->currencyInput = strtoupper(trim((string) $rawCurrency));
        }

        // Customer details
        if (filled($customerName)) {
            $this->customerName = $customerName;
        }
        if (filled($customerEmail)) {
            $this->customerEmail = $customerEmail;
        }
        if (filled($customerPhone)) {
            $this->customerPhone = $customerPhone;
        }

        // Order & Metadata
        $this->orderTitle = (string) ($orderTitle ?: request()->query('order_title', request()->query('order', 'Order Payment')));
        $this->description = (string) ($description ?: request()->query('description', ''));
        $this->returnUrl = $returnUrl ?: request()->query('return_url', null);
        $this->metadata = $metadata;

        // Coupon
        $this->couponCode = (string) ($coupon ?: request()->query('coupon', $this->coupon ?? ''));
    }

    private function determineStartingStep(?int $explicitStep): void
    {
        if ($explicitStep !== null && $explicitStep >= 1 && $explicitStep <= 3) {
            $this->step = $explicitStep;

            return;
        }

        // If URL provided step
        $urlStep = (int) request()->query('step', 0);
        if ($urlStep >= 1 && $urlStep <= 3) {
            $this->step = $urlStep;

            return;
        }

        $hasAmount = $this->getAmountInCents() > 0;
        $hasCustomer = filled($this->customerEmail) && filter_var($this->customerEmail, FILTER_VALIDATE_EMAIL) !== false;

        if ($hasAmount && $hasCustomer) {
            $this->step = 3; // Jump straight to payment & review
        } elseif ($hasAmount) {
            $this->step = 2; // Jump to customer details
        } else {
            $this->step = 1;
        }
    }

    private function autoSelectGateway(): void
    {
        $enabled = $this->getEnabledGatewayCodes();

        if (empty($enabled)) {
            return;
        }

        if (filled($this->selectedGateway) && in_array($this->selectedGateway, $enabled, true)) {
            return;
        }

        // Match first gateway supporting the currency
        $resolver = app(GatewayResolver::class);
        foreach ($enabled as $code) {
            $cfg = $resolver->getGatewayConfig($code);
            if ($cfg && $cfg->supportsCurrency($this->currencyInput)) {
                $this->selectedGateway = $code;

                return;
            }
        }

        $this->selectedGateway = $enabled[0];
    }

    private function applyInitialCoupon(): void
    {
        if (filled($this->couponCode)) {
            $this->applyCoupon();
        }
    }

    public function nextStep(): void
    {
        $this->clearError();

        if ($this->step === 1) {
            if ($this->getAmountInCents() <= 0) {
                $this->showError('Please enter a valid amount greater than 0.');

                return;
            }

            if (strlen($this->getCurrencyCode()) !== 3) {
                $this->showError('Please select a valid 3-letter currency code.');

                return;
            }

            $this->autoSelectGateway();
            $this->step = 2;

            return;
        }

        if ($this->step === 2) {
            if (blank($this->customerEmail) || ! filter_var($this->customerEmail, FILTER_VALIDATE_EMAIL)) {
                $this->showError('Please provide a valid email address.');

                return;
            }

            $this->step = 3;
        }
    }

    public function previousStep(): void
    {
        $this->clearError();
        $this->step = max(1, $this->step - 1);
    }

    public function goToStep(int $targetStep): void
    {
        if ($targetStep < 1 || $targetStep > 3) {
            return;
        }

        if ($targetStep > 1 && $this->getAmountInCents() <= 0) {
            $this->showError('Please complete Step 1 before continuing.');

            return;
        }

        if ($targetStep === 3 && (blank($this->customerEmail) || ! filter_var($this->customerEmail, FILTER_VALIDATE_EMAIL))) {
            $this->showError('Please provide a valid email in Step 2 before proceeding to payment.');

            return;
        }

        $this->clearError();
        $this->step = $targetStep;
    }

    public function applyCoupon(): void
    {
        $this->clearError();

        if (blank($this->couponCode)) {
            $this->showError('Please enter a coupon code.');

            return;
        }

        $coupon = Coupon::where('code', trim($this->couponCode))->first();

        if (! $coupon) {
            $this->showError('Invalid or expired coupon code.');

            return;
        }

        $amountCents = $this->getAmountInCents();

        if ($amountCents <= 0) {
            $this->showError('Please enter a valid order amount first.');

            return;
        }

        $currency = $this->getCurrencyCode();

        if (! $coupon->canApply(auth()->id(), $amountCents, $currency)) {
            $this->showError('This coupon cannot be applied to your order.');

            return;
        }

        $discount = $coupon->calculateDiscount($amountCents);

        if ($discount <= 0 && ! $coupon->is_free_shipping) {
            $this->showError('This coupon does not apply to this amount.');

            return;
        }

        $this->appliedCouponCode = $coupon->code;
        $this->appliedDiscountCents = $discount;
        $this->hasFreeShipping = (bool) $coupon->is_free_shipping;
        $this->couponCode = '';
    }

    public function removeCoupon(): void
    {
        $this->appliedCouponCode = null;
        $this->appliedDiscountCents = 0;
        $this->hasFreeShipping = false;
    }

    public function updatedCurrencyInput(): void
    {
        $this->currencyInput = strtoupper(trim($this->currencyInput));
        $this->autoSelectGateway();
        $this->recalculateCouponIfApplied();
    }

    public function updatedAmountInput(): void
    {
        $this->recalculateCouponIfApplied();
    }

    private function recalculateCouponIfApplied(): void
    {
        if (filled($this->appliedCouponCode)) {
            $coupon = Coupon::where('code', $this->appliedCouponCode)->first();
            $amountCents = $this->getAmountInCents();

            if ($coupon && $amountCents > 0 && $coupon->canApply(auth()->id(), $amountCents, $this->currencyInput)) {
                $this->appliedDiscountCents = $coupon->calculateDiscount($amountCents);
            } else {
                $this->removeCoupon();
            }
        }
    }

    public function getAmountInCents(): int
    {
        $clean = trim($this->amountInput);

        if (! is_numeric($clean)) {
            return 0;
        }

        return (int) round((float) $clean * 100);
    }

    public function getCurrencyCode(): string
    {
        return strtoupper(trim($this->currencyInput ?: 'USD'));
    }

    #[Computed]
    public function finalAmountCents(): int
    {
        return max(0, $this->getAmountInCents() - $this->appliedDiscountCents);
    }

    #[Computed]
    public function formattedAmount(): string
    {
        return number_format($this->finalAmountCents / 100, 2);
    }

    #[Computed]
    public function formattedDiscount(): string
    {
        return number_format($this->appliedDiscountCents / 100, 2);
    }

    /**
     * @return array<int, array{code: string, label: string, icon: string, currencies: array<string>, checkout_redirect: bool}>
     */
    #[Computed]
    public function enabledGateways(): array
    {
        $resolver = app(GatewayResolver::class);
        $codes = $resolver->getEnabledGateways();

        return collect($codes)->map(function (string $code) use ($resolver): array {
            $config = $resolver->getGatewayConfig($code);

            if ($config === null) {
                return ['code' => $code, 'label' => $code, 'icon' => '', 'currencies' => [], 'checkout_redirect' => false];
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

    /**
     * @return array<string>
     */
    private function getEnabledGatewayCodes(): array
    {
        return app(GatewayResolver::class)->getEnabledGateways();
    }

    public function charge(): ?RedirectResponse
    {
        $this->clearError();
        $this->isRedirecting = false;

        if (blank($this->selectedGateway)) {
            $this->showError('Please select a payment gateway.');

            return null;
        }

        $amountCents = $this->finalAmountCents;

        if ($amountCents <= 0) {
            $this->showError('Payable amount must be greater than zero.');

            return null;
        }

        if (blank($this->customerEmail) || ! filter_var($this->customerEmail, FILTER_VALIDATE_EMAIL)) {
            $this->showError('Please provide a valid customer email.');
            $this->step = 2;

            return null;
        }

        $payload = [
            'amount_cents' => $amountCents,
            'currency' => $this->getCurrencyCode(),
            'gateway' => $this->selectedGateway,
            'customer_email' => $this->customerEmail,
            'customer_name' => $this->customerName ?: null,
            'customer_phone' => $this->customerPhone ?: null,
            'description' => $this->description ?: "Payment for {$this->orderTitle}",
            'return_url' => $this->returnUrl,
            'metadata' => array_merge($this->metadata, [
                'order_title' => $this->orderTitle,
                'original_amount_cents' => $this->getAmountInCents(),
                'discount_cents' => $this->appliedDiscountCents,
                'applied_coupon' => $this->appliedCouponCode,
                'has_free_shipping' => $this->hasFreeShipping,
            ]),
        ];

        try {
            $result = ChargePaymentAction::run($payload);

            if (! $result->success) {
                $this->showError($result->errorMessage ?? 'Payment failed. Please try again.');

                return null;
            }

            if (filled($result->redirectUrl)) {
                $this->isRedirecting = true;

                return $this->redirect($result->redirectUrl, navigate: false);
            }

            if (filled($result->orderReference)) {
                session()->flash('payment_success', $result->orderReference);
                $successRoute = config('payment-gateway.routes.names.success', 'payment.success');

                if (Route::has($successRoute)) {
                    return $this->redirect(route($successRoute, ['reference' => $result->orderReference]), navigate: true);
                }
            }

            return null;
        } catch (Throwable $e) {
            $this->showError("Payment Error: {$e->getMessage()}");

            return null;
        }
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
        return view('payment-gateway::livewire.checkout', [
            'step' => $this->step,
            'finalAmountCents' => $this->finalAmountCents,
            'formattedAmount' => $this->formattedAmount,
            'formattedDiscount' => $this->formattedDiscount,
            'enabledGateways' => $this->enabledGateways,
        ]);
    }
}
