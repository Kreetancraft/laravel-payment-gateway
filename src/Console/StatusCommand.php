<?php

namespace Kreetancraft\PaymentGateway\Console;

use Illuminate\Console\Command;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Models\Gateway;

/**
 * Say whether this application can actually take a payment.
 *
 * Modelled on the monolith's `hbl:status`, and for the same reason: every way a
 * payment gateway is misconfigured looks identical from the outside — a buyer
 * reaches the bank and is turned away, or nothing happens at all. Checking each
 * requirement by name turns that into one line.
 *
 * Exits non-zero when something is missing, so a deploy can gate on it.
 */
class StatusCommand extends Command
{
    protected $signature = 'payment-gateway:status';

    protected $description = 'Check that the configured payment gateways can actually be used';

    public function handle(GatewayResolver $resolver): int
    {
        $this->newLine();
        $this->line(' <fg=gray>payable types:</> '.($this->payables() ?: '<fg=yellow>none configured — checkout will refuse everything</>'));
        $this->newLine();

        $gateways = Gateway::query()->where('enabled', true)->get();

        if ($gateways->isEmpty()) {
            $this->components->error('No gateway is enabled. Nothing can take a payment.');

            return self::FAILURE;
        }

        $ok = true;

        foreach ($gateways as $gateway) {
            $ok = $this->reportGateway($gateway) && $ok;
        }

        $this->newLine();

        if (! $ok) {
            $this->components->error('At least one enabled gateway is not ready.');

            return self::FAILURE;
        }

        $this->components->info('Every enabled gateway looks ready.');

        return self::SUCCESS;
    }

    private function reportGateway(Gateway $gateway): bool
    {
        $environment = (string) ($gateway->environment ?: 'demo');
        $live = in_array(strtolower($environment), ['production', 'live'], true);

        $this->line(sprintf(
            ' <options=bold>%s</> %s',
            $gateway->code,
            $live ? '<fg=red;options=bold>LIVE</>' : '<fg=yellow>TEST MODE</>'
        ));

        $required = match ($gateway->code) {
            'stripe' => ['secret_key', 'publishable_key', 'webhook_secret'],
            'himalayan' => [
                'office_id',
                'api_key',
                // Without this every outbound JWE carries an empty `kid` and PACO
                // rejects the request. It was missing for the life of the package.
                'encryption_key_id',
                'merchant_signing_key',
                'merchant_decryption_key',
                'paco_encryption_public_key',
                'paco_signing_public_key',
            ],
            default => [],
        };

        $ok = true;

        foreach ($required as $credential) {
            $present = filled($gateway->getCredential($credential));

            $this->line(sprintf(
                '   %s %s',
                $present ? '<fg=green>✓</>' : '<fg=red>✗</>',
                $credential
            ));

            $ok = $present && $ok;
        }

        if ($gateway->code === 'stripe') {
            $version = config('payment-gateway.gateways.stripe.api_version');
            $this->line('   <fg=gray>api version:</> '.($version ?: 'SDK default'));
        }

        return $ok;
    }

    private function payables(): string
    {
        $payables = array_keys((array) config('payment-gateway.payables', []));

        return implode(', ', $payables);
    }
}
