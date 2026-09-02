<?php

namespace Kreetancraft\PaymentGateway\Support;

use Kreetancraft\PaymentGateway\Models\Coupon;

/**
 * Condition checker for coupon conditions (from discountify)
 * Supports:
 * - min_order_amount
 * - Model attachments (product/category/user)
 * - Custom conditions via Closure or class-based conditions
 */
class ConditionChecker
{
    public function check(Coupon|array $coupon, array $context = []): bool
    {
        $conditions = is_array($coupon) ? $coupon : ($coupon->conditions ?? []);

        if (empty($conditions)) {
            return true;
        }

        // Check min_order_amount
        if (isset($conditions['min_order_amount'])) {
            $amount = $context['amount_cents'] ?? 0;
            if ($amount < $conditions['min_order_amount']) {
                return false;
            }
        }

        // Check model attachments (product/category/user)
        if (isset($conditions['models'])) {
            if (! $this->checkModelAttachments($conditions['models'], $context, $coupon->id ?? 0)) {
                return false;
            }
        }

        // Check currency restrictions
        if (isset($conditions['currencies'])) {
            $currency = $context['currency'] ?? '';
            if (! in_array(strtoupper($currency), array_map('strtoupper', $conditions['currencies']))) {
                return false;
            }
        }

        // Check time windows (from pixellair)
        if (isset($conditions['time_windows'])) {
            if (! $this->checkTimeWindows($conditions['time_windows'])) {
                return false;
            }
        }

        // Check user restrictions
        if (isset($conditions['user_ids']) && isset($context['user_id'])) {
            $userId = $context['user_id'];
            if (! in_array($context['user_id'], $conditions['user_ids'])) {
                return false;
            }
        }

        // Check custom conditions (Closure or class-based)
        if (isset($conditions['custom'])) {
            foreach ($conditions['custom'] as $condition) {
                if ($condition instanceof \Closure) {
                    if (! $condition($context)) {
                        return false;
                    }
                } elseif (is_string($condition) && class_exists($condition)) {
                    $instance = app($condition);
                    if (method_exists($instance, 'check') && ! $instance->check($context)) {
                        return false;
                    }
                }
            }
        }

        // Check usage limits per time window (from pixellair)
        if (isset($conditions['time_windows'])) {
            if (! $this->checkTimeWindowLimits($conditions['time_windows'])) {
                return false;
            }
        }

        // Check custom conditions via class-based
        if (isset($conditions['condition_class'])) {
            $class = $conditions['condition_class'];
            if (class_exists($class)) {
                $instance = app($class);
                if (method_exists($instance, 'check') && ! $instance->check($context)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function checkModelAttachments(array $models, array $context, int $couponId = 0): bool
    {
        foreach ($models as $modelConfig) {
            $modelClass = $modelConfig['model'] ?? null;
            $relation = $modelConfig['relation'] ?? 'discounts';
            $condition = $modelConfig['condition'] ?? 'any'; // any, all

            if (! $modelClass || ! class_exists($modelClass)) {
                continue;
            }

            $modelIds = $context[$modelConfig['context_key'] ?? 'model_ids'] ?? [];

            if (empty($modelIds)) {
                return false;
            }

            $model = new $modelClass;
            $query = $model->whereIn('id', $modelIds)->whereHas($relation, function ($q) use ($couponId) {
                $q->where('coupon_id', $couponId);
            });

            $count = $query->count();
            $total = count($modelIds);

            if ($condition === 'all' && $count !== $total) {
                return false;
            }

            if ($condition === 'any' && $count === 0) {
                return false;
            }
        }

        return true;
    }

    private function checkTimeWindows(array $timeWindows): bool
    {
        $now = now();

        foreach ($timeWindows as $window) {
            $start = $window['start'] ?? null;
            $end = $window['end'] ?? null;
            $days = $window['days'] ?? null; // e.g., ['monday', 'friday']
            $timezone = $window['timezone'] ?? config('app.timezone');

            if ($start && $now->lt($start)) {
                return false;
            }

            if ($end && $now->gt($end)) {
                return false;
            }

            if ($days) {
                $dayOfWeek = strtolower($now->format('l'));
                if (! in_array(strtolower($dayOfWeek), array_map('strtolower', $days))) {
                    return false;
                }
            }
        }

        return true;
    }

    private function checkTimeWindowLimits(array $timeWindows): bool
    {
        // Check usage limits per time window (from pixellair)
        foreach ($timeWindows as $window) {
            $period = $window['period'] ?? 'day'; // day, week, month
            $limit = $window['limit'] ?? null;

            if (! $limit) {
                continue;
            }

            // This would check usage in the current time window
            // Implementation depends on your usage tracking
        }

        return true;
    }
}
