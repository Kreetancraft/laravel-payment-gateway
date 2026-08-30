<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique()->index();
            $table->string('name')->nullable();
            $table->text('description')->nullable();

            // Discount type and value
            $table->enum('type', [
                'percentage', 'fixed', 'buy_x_get_y', 'tiered', 'free_shipping'
            ])->default('percentage');
            $table->unsignedInteger('value')->default(0);
            $table->unsignedInteger('max_discount_amount')->nullable();
            $table->unsignedInteger('min_order_amount')->nullable();

            // Usage limits
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->json('user_ids')->nullable();
            $table->unsignedInteger('usage_count')->default(0);

            // Validity
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            // Conditions (for tiered, buy_x_get_y, etc.)
            $table->json('conditions')->nullable();

            // Stacking & shipping
            $table->boolean('is_stackable')->default(false);
            $table->boolean('is_free_shipping')->default(false);

            // Code prefix (pixellair: T=time, A=amount, P=percentage, F=fixed, B=buy_x_get_y)
            $table->char('code_prefix', 1)->nullable();

            // Time windows (pixellair: recurring time-based restrictions)
            $table->json('time_windows')->nullable();

            // Model attachment (discountify: polymorphic attachment)
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('model_type')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['is_active', 'starts_at', 'expires_at']);
            $table->index(['code', 'is_active']);
            $table->index('expires_at');
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};