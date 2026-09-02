<?php

namespace Kreetancraft\PaymentGateway;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Where this package's screens render, and where "Dashboard" points.
 *
 * Automatically detects the host application's layout conventions.
 */
class Layout
{
    /**
     * Layout names to try when the configured one does not resolve.
     *
     * @var list<string>
     */
    public const CONVENTIONS = [
        'components.layouts.app',
        'layouts.app',
        'components.layouts.admin',
        'layouts.admin',
    ];

    public static function admin(): string
    {
        return self::resolve(
            config('payment-gateway.layouts.admin'),
            'payment-gateway.layouts.admin',
            self::CONVENTIONS,
        );
    }

    /**
     * Where the "Dashboard" breadcrumb points.
     */
    /**
     * Where the buyer-facing checkout renders.
     *
     * Separate from admin() because a payment page should not be wrapped in your
     * admin chrome — a buyer is not signed in and should not see a sidebar of
     * staff links. Falls back to the same conventions, so it works with no
     * configuration at all.
     */
    public static function checkout(): string
    {
        return self::resolve(
            config('payment-gateway.layouts.checkout') ?? config('payment-gateway.layouts.admin'),
            'payment-gateway.layouts.checkout',
            self::CONVENTIONS,
        );
    }

    public static function home(): string
    {
        $home = (string) config('payment-gateway.routes.home', 'dashboard');

        if ($home === '') {
            return '/';
        }

        if (Route::has($home)) {
            return route($home);
        }

        return str_starts_with($home, '/') || str_contains($home, '://') || str_starts_with($home, '#')
            ? $home
            : '/';
    }

    /**
     * @param  list<string>  $conventions
     */
    private static function resolve(?string $configured, string $key, array $conventions): string
    {
        if (is_string($configured) && $configured !== '' && View::exists($configured)) {
            return $configured;
        }

        foreach ($conventions as $candidate) {
            if (View::exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf(
            'No layout to render into. Set `%s` in config/%s.php to one of your layout views. '
            .'Tried: %s. This package ships no layout by design — its screens render into yours.',
            $key,
            explode('.', $key)[0],
            implode(', ', array_values(array_unique(array_filter(
                array_merge([$configured], $conventions)
            )))),
        ));
    }
}
