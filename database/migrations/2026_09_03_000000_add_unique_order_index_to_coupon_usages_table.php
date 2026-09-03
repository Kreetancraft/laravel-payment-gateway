<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One coupon may be redeemed against one order once.
     *
     * `CouponUsage::recordUsage()` was a bare `create()`, so nothing stopped the
     * same coupon being recorded against the same order twice — a double-clicked
     * apply, a retried job, a redelivered webhook. Each duplicate counted again
     * toward the coupon's usage cap and its reported discount total, so a coupon
     * limited to 100 redemptions could be exhausted by 50 customers.
     *
     * The `usage_count` column already exists for the case where one order
     * genuinely uses a coupon more than once; that is a number to raise, not a
     * second row to insert.
     */
    public function up(): void
    {
        $this->removeDuplicates();

        Schema::table('coupon_usages', function (Blueprint $table): void {
            $table->unique(['coupon_id', 'order_type', 'order_id'], 'coupon_usages_order_unique');
        });
    }

    /**
     * Fold any duplicates that already exist into the row that came first.
     *
     * Without this the index cannot be created on a table that has been in use.
     * The surviving row keeps the combined count and discount, so the coupon's
     * totals stay exactly as they were.
     */
    private function removeDuplicates(): void
    {
        $duplicates = DB::table('coupon_usages')
            ->select('coupon_id', 'order_type', 'order_id')
            ->groupBy('coupon_id', 'order_type', 'order_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $group) {
            $rows = DB::table('coupon_usages')
                ->where('coupon_id', $group->coupon_id)
                ->where('order_type', $group->order_type)
                ->where('order_id', $group->order_id)
                ->orderBy('id')
                ->get();

            $keep = $rows->first();

            DB::table('coupon_usages')->where('id', $keep->id)->update([
                'usage_count' => $rows->sum('usage_count'),
                'amount_discounted_cents' => $rows->sum('amount_discounted_cents'),
            ]);

            DB::table('coupon_usages')
                ->whereIn('id', $rows->skip(1)->pluck('id'))
                ->delete();
        }
    }
};
