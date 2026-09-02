<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two hot queries both filter on status and order by created_at, and the
     * table has no index that serves them.
     *
     * `payment-gateway:reconcile` runs on a schedule and asks for pending
     * payments older than N minutes, oldest first. The transactions screen
     * filters by status and orders by date. On a table with a year of payments
     * in it both become full scans — and the sweep is the one that has to stay
     * cheap, because it runs every few minutes forever.
     *
     * The existing indexes are (user_id, status) and (gateway, status), neither
     * of which helps: status is the second column in both, so a query filtering
     * on status alone cannot use either.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['status', 'created_at']);
        });
    }
};
