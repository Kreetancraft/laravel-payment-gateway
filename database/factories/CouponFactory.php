<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\CouponUsage;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['percentage', 'fixed', 'buy_x_get_y', 'tiered', 'free_shipping']);
        $currencies = ['USD', 'EUR', 'GBP', 'NPR', 'THB'];

        return [
            'uuid' => fake()->uuid(),
            'code' => strtoupper(fake()->unique()->bothify('????-####')),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'type' => $type,
            'value' => match ($type) {
                'percentage' => fake()->numberBetween(1, 100),
                'fixed' => fake()->numberBetween(100, 50000),
                'buy_x_get_y' => fake()->numberBetween(1, 5),
                'tiered' => fake()->numberBetween(1, 100),
                'free_shipping' => 0,
                default => 0,
            },
            'max_discount_amount' => fake()->optional()->numberBetween(1000, 100000),
            'min_order_amount' => fake()->optional()->numberBetween(1000, 50000),
            'usage_limit' => fake()->optional()->numberBetween(10, 1000),
            'usage_limit_per_user' => fake()->optional()->numberBetween(1, 10),
            'user_ids' => null,
            'starts_at' => fake()->optional()->dateTimeBetween('-30 days', '+30 days'),
            'expires_at' => fake()->optional()->dateTimeBetween('+1 day', '+1 year'),
            'usage_count' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'max_discount_amount' => fake()->optional()->numberBetween(1000, 50000),
            'min_order_amount' => fake()->optional()->numberBetween(1000, 100000),
            'conditions' => null,
            'is_stackable' => false,
            'is_free_shipping' => $type === 'free_shipping',
        ];
    }

    public function percentage(int $value = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => $value,
        ]);
    }

    public function fixed(int $valueCents = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'value' => $valueCents,
        ]);
    }

    public function buyXGetY(int $buy = 2, int $get = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'buy_x_get_y',
            'value' => $buy,
            'conditions' => array_merge(
                $attributes['conditions'] ?? [],
                ['buy' => $buy, 'get' => $get]
            ),
        ]);
    }

    public function tiered(array $tiers = []): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'tiered',
            'conditions' => array_merge(
                $attributes['conditions'] ?? [],
                ['tiers' => $tiers ?: [
                    ['min' => 100000, 'value' => 5],
                    ['min' => 500000, 'value' => 10],
                ]],
            ),
        ]);
    }

    public function freeShipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'free_shipping',
            'is_free_shipping' => true,
            'value' => 0,
        ]);
    }

    public function stackable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_stackable' => true,
        ]);
    }

    public function freeShipping(): static
    {
        return $this->freeShipping();
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
            'is_active' => false,
        ]);
    }

    public function forCurrency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'conditions' => array_merge(
                $attributes['conditions'] ?? [],
                ['currencies' => [strtoupper($currency)]]
            ),
        ]);
    }

    public function withUserIds(array $userIds): static
    {
        return $this->state(fn (array $attributes) => [
            'user_ids' => $userIds,
        ]);
    }

    public function withUsageLimit(int $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_limit' => $limit,
        ]);
    }

    public function perUserLimit(int $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_limit_per_user' => $limit,
        ]);
    }

    public function withUserIds(array $userIds): static
    {
        return $this->withUserIds($userIds);
    }

    public function withUsageLimit(int $limit): static
    {
        return $this->withUsageLimit($limit);
    }

    public function perUserLimit(int $limit): static
    {
        return $this->perUserLimit($limit);
    }
}