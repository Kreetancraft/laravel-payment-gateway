<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kreetancraft\PaymentGateway\Models\CouponUsage;
use Kreetancraft\PaymentGateway\Models\Coupon;

/**
 * @extends Factory<CouponUsage>
 */
class CouponUsageFactory extends Factory
{
    protected $model = CouponUsage::class;

    public function definition(): array
    {
        return [
            'coupon_id' => Coupon::factory(),
            'user_id' => null,
            'order_type' => 'App\Models\Order',
            'order_id' => fake()->numberBetween(1, 1000),
            'usage_count' => fake()->numberBetween(1, 5),
            'amount_discounted_cents' => fake()->numberBetween(100, 50000),
            'currency' => fake()->randomElement(['USD', 'EUR', 'GBP', 'NPR', 'THB']),
            'metadata' => [],
        ];
    }

    public function forCoupon(Coupon $coupon): static
    {
        return $this->state(fn (array $attributes) => [
            'coupon_id' => $coupon->id,
        ]);
    }

    public function forUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    public function forOrder(string $orderType, int $orderId): static
    {
        return $this->state(fn (array $attributes) => [
            'order_type' => $orderType,
            'order_id' => $orderId,
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ]);
    }
}