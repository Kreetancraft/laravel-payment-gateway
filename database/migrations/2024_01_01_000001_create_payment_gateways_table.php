<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index(); // e.g., 'stripe', 'himalayan'
            $table->string('label'); // Display name
            $table->string('icon')->nullable(); // Icon URL
            $table->boolean('enabled')->default(false);
            $table->string('class'); // Gateway class FQCN
            $table->json('currencies')->nullable(); // Supported currencies
            $table->json('capabilities')->nullable(); // charge, refund, webhook, verify, subscription
            $table->boolean('checkout_redirect')->default(false);
            $table->boolean('supports_subscriptions')->default(false);
            $table->string('environment')->default('demo'); // demo, production

            // Encrypted credentials (encrypted via Laravel's Crypt)
            $table->json('credentials')->nullable(); // Encrypted sensitive data: API keys, secrets, keys, paths

            // Gateway configuration fields definition (for admin UI)
            $table->json('config_fields')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index('enabled');
        });
    }
};
