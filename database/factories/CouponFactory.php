<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kreetancraft\PaymentGateway\Models\Coupon;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'code' => strtoupper(fake()->unique()->bothify('????-####')),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'type' => 'percentage',
            'value' => 10,
            'max_discount_amount' => null,
            'min_order_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'user_ids' => null,
            'starts_at' => null,
            'expires_at' => null,
            'usage_count' => 0,
            'is_active' => true,
            'conditions' => null,
            'is_stackable' => false,
            'is_free_shipping' => false,
        ];
    }

    public function percentage(int $value = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => $value,
            'is_free_shipping' => false,
        ]);
    }

    public function fixed(int $valueCents = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'value' => $valueCents,
            'is_free_shipping' => false,
        ]);
    }

    public function buyXGetY(int $buy = 2, int $get = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'buy_x_get_y',
            'value' => $buy,
            'is_free_shipping' => false,
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
            'is_free_shipping' => false,
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

    public function withUsageLimit(int $limit = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_limit' => $limit,
        ]);
    }

    public function withPerUserLimit(int $limit = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_limit_per_user' => $limit,
        ]);
    }

    public function forUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_ids' => [$userId],
        ]);
    }

    public function forUsers(array $userIds): static
    {
        return $this->state(fn (array $attributes) => [
            'user_ids' => $userIds,
        ]);
    }

    public function withMinOrder(int $cents = 5000): static
    {
        return $this->state(fn (array $attributes) => [
            'min_order_amount' => $cents,
        ]);
    }

    public function withMaxDiscount(int $cents = 5000): static
    {
        return $this->state(fn (array $attributes) => [
            'max_discount_amount' => $cents,
        ]);
    }
}
