<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a payment to the thing it pays for.
 *
 * Without this there was nothing to check an amount against, so the amount came
 * from the request — on a public route. Nullable, because a host that has not
 * adopted the Payable contract yet keeps working; the checkout path requires it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->nullableMorphs('payable');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropMorphs('payable');
        });
    }
};
