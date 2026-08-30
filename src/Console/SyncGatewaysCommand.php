<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Console;

use Illuminate\Console\Command;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;

class SyncGatewaysCommand extends Command
{
    protected $signature = 'payment-gateway:sync {--gateway= : Specific gateway to sync} {--to-config : Persist changes to config file} {--to-database : Sync from config to database}';

    protected $description = 'Sync gateway configurations between config and database';

    public function handle(): int
    {
        $gatewayCode = $this->option('gateway');
        $toConfig = (bool) $this->option('to-config');
        $toDatabase = (bool) $this->option('to-database');

        if (! $toConfig && ! $toDatabase) {
            $this->error('Please specify either --to-config or --to-database');
            return self::FAILURE;
        }

        if ($toConfig && $toDatabase) {
            $this->error('Cannot sync both directions at once. Choose --to-config or --to-database.');
            return self::FAILURE;
        }

        $resolver = app(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class);
        $enabled = $resolver->getEnabledGateways();

        if ($this->option('gateway')) {
            if (!in_array($gatewayCode, $enabled, true)) {
                $this->error("Gateway [{$gatewayCode}] is not enabled or does not exist.");
                return self::FAILURE;
            }
            $this->syncSingle($gatewayCode, $toConfig, $toDatabase);
        } else {
            foreach ($enabled as $code) {
                $this->syncSingle($code, $toConfig, $toDatabase);
            }
        }

        $this->info('Gateway configurations synced successfully.');

        return self::SUCCESS;
    }

    private function syncSingle(string $code, bool $toConfig, bool $toDatabase): void
    {
        if ($toDatabase) {
            $this->syncConfigToDatabase($code);
        } elseif ($toConfig) {
            $this->syncDatabaseToConfig($code);
        }
    }

    private function syncConfigToDatabase(string $code): void
    {
        $config = config("payment-gateway.gateways.{$code}", []);

        if (empty($config)) {
            $this->warn("Gateway [{$code}] not found in config.");
            return;
        }

        $gateway = \Kreetancraft\PaymentGateway\Models\Gateway::updateOrCreate(
            ['code' => $code],
            [
                'label' => $config['label'] ?? $code,
                'icon' => $config['icon'] ?? null,
                'enabled' => $config['enabled'] ?? false,
                'class' => $config['class'] ?? null,
                'currencies' => $config['currencies'] ?? [],
                'capabilities' => $config['capabilities'] ?? [],
                'checkout_redirect' => $config['checkout_redirect'] ?? false,
                'supports_subscriptions' => $config['supports_subscriptions'] ?? false,
                'environment' => $config['environment'] ?? 'demo',
                'config_fields' => $config['config_fields'] ?? [],
                'credentials' => $config['credentials'] ?? [],
                'enabled' => $config['enabled'] ?? false,
            ]
        );

        $this->info("  - Synced [{$code}] to database");
    }

    private function syncDatabaseToConfig(string $code): void
    {
        $gateway = \Kreetancraft\PaymentGateway\Models\Gateway::where('code', $code)->first();

        if (!$gateway) {
            $this->warn("Gateway [{$code}] not found in database.");
            return;
        }

        $this->line("  - Would sync [{$code}] to config (manual step required)");
        $this->warn("  Config file sync not fully implemented. Manual edit required.");
    }
}