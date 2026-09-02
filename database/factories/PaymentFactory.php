<?php

namespace Kreetancraft\PaymentGateway\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Kreetancraft\PaymentGateway\Models\Payment;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $amountCents = $this->faker->numberBetween(100, 100000);
        $currency = $this->faker->randomElement(['USD', 'NPR', 'EUR', 'GBP']);
        $gateway = $this->faker->randomElement(['stripe', 'himalayan']);

        return [
            'uuid' => (string) Str::uuid(),
            'reference' => 'PMT-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => null,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'gateway' => $gateway,
            'gateway_reference' => $gateway === 'stripe' ? 'pi_'.Str::random(24) : 'ORD-'.Str::upper(Str::random(8)),
            'status' => $this->faker->randomElement(['pending', 'succeeded', 'failed', 'canceled']),
            'refunded_amount_cents' => 0,
            'idempotency_key' => hash('sha256', (string) Str::uuid()),
            'paid_at' => null,
            'refunded_at' => null,
            'customer_email' => $this->faker->safeEmail(),
            'customer_name' => $this->faker->name(),
            'customer_phone' => $this->faker->phoneNumber(),
            'customer_address' => $this->faker->address(),
            'description' => $this->faker->sentence(),
            'metadata' => [
                'order_id' => $this->faker->uuid(),
                'notes' => $this->faker->sentence(),
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'succeeded',
            'paid_at' => now(),
            'gateway_reference' => $attributes['gateway'] === 'stripe'
                ? 'pi_'.Str::random(24)
                : 'ORD-'.Str::upper(Str::random(8)),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'failed',
            'paid_at' => null,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'refunded',
            'refunded_amount_cents' => $attributes['amount_cents'],
            'refunded_at' => now(),
        ]);
    }

    public function partiallyRefunded(): static
    {
        return $this->state(function (array $attributes): array {
            $partial = (int) round($attributes['amount_cents'] / 2);

            return [
                'status' => 'partially_refunded',
                'refunded_amount_cents' => $partial,
                'refunded_at' => now(),
            ];
        });
    }

    public function forGateway(string $gateway): static
    {
        return $this->state(fn (array $attributes): array => [
            'gateway' => $gateway,
        ]);
    }

    public function forCurrency(string $currency): static
    {
        return $this->state(fn (array $attributes): array => [
            'currency' => strtoupper($currency),
        ]);
    }
}
