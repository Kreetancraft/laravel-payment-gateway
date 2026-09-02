<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\DemoInvoice;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The bench's own payable, registered under the alias the checkout URL
        // uses. A host does exactly this in config/payment-gateway.php.
        config()->set('payment-gateway.payables.invoice', DemoInvoice::class);

        // The bench has no Vite build, so the package's buyer-facing pages take
        // their stylesheet from here instead.
        config()->set('payment-gateway.assets_view', 'payment-assets');

        // Prepended on the finder itself, not via config: the finder is already
        // built by the time boot() runs, so setting `view.paths` here changes
        // nothing and Layout::admin() still finds no layout.
        //
        // On the default path rather than behind a namespace, because anonymous
        // components like <x-layouts.app> only resolve there — and because the
        // package's own screens look for `components.layouts.app` through
        // Layout::CONVENTIONS. One registration serves both.
        $this->app['view']->getFinder()->prependLocation(__DIR__.'/../../resources/views');
    }
}
