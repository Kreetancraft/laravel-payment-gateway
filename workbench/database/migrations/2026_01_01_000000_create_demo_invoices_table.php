<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
            $table->string('currency', 3)->default('NPR');
            $table->unsignedInteger('total_cents');
            $table->unsignedInteger('paid_cents')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_invoices');
    }
};
