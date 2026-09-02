<?php

namespace Kreetancraft\PaymentGateway\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'payment-gateway:install {--force : Overwrite existing files without confirmation} {--without-migrate : Skip running migrations}';

    protected $description = 'Install the Payment Gateway package resources';

    public function handle(): int
    {
        $this->info('Installing Payment Gateway...');

        $force = (bool) $this->option('force');

        $this->call('vendor:publish', [
            '--tag' => 'payment-gateway-config',
            '--force' => $force,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'payment-gateway-views',
            '--force' => $force,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'payment-gateway-migrations',
            '--force' => $force,
        ]);

        if (! $this->option('without-migrate')) {
            $this->info('Running migrations...');

            $this->call('migrate', [
                '--force' => true,
            ]);
        }

        $this->call('payment-gateway:sync', ['--to-database' => true]);

        $this->injectNavComponent();

        $this->info('Payment Gateway installed successfully.');
        $this->line('  - Config: config/payment-gateway.php');
        $this->line('  - Views: resources/views/vendor/payment-gateway');
        $this->line('  - Routes: payment/* (see config payment-gateway.routes.prefix)');
        $this->line('Next: configure gateways in admin UI or run `php artisan payment-gateway:sync --to-database`');

        return self::SUCCESS;
    }

    private function injectNavComponent(): void
    {
        $candidates = [
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/layouts/navigation.blade.php'),
            resource_path('views/components/layouts/app.blade.php'),
        ];

        $componentTag = '<x-payment-gateway::payment-gateway />';

        foreach ($candidates as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $contents = File::get($path);

            if (str_contains($contents, 'payment-gateway::payment-gateway') || str_contains($contents, 'payment-gateway:payment-gateway')) {
                $this->line("  - Nav already present in {$path}");

                return;
            }

            if (str_contains($contents, '</nav>')) {
                $updated = str_replace('</nav>', "    <x-payment-gateway::payment-gateway />\n</nav>", $contents);
                File::put($path, $updated);
                $this->line("  - Injected nav component into {$path}");

                return;
            }

            if (str_contains($contents, '<body')) {
                $updated = str_replace('<body', "{$componentTag}\n<body", $contents);
                File::put($path, $updated);
                $this->line("  - Injected nav component into {$path} (before <body>)");

                return;
            }
        }

        $this->line('  - Skipped nav injection: no layout found. Add <x-payment-gateway::payment-gateway /> to your layout manually.');
    }
}
