<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('receipt_key')->nullable()->unique();
        });
        Schema::table('payment_proofs', function (Blueprint $table) {
            // Unbound historical receipts stay preserved but cannot attach to a new order.
            $table->uuid('order_receipt_key')->nullable()->index();
        });

        DB::table('orders')->orderBy('id')->chunkById(200, function ($orders) {
            DB::transaction(function () use ($orders) {
                foreach ($orders as $order) {
                    $key = (string) Str::uuid();
                    DB::table('orders')->where('id', $order->id)->update(['receipt_key' => $key]);
                    if ($order->created_at !== null) {
                        // A receipt predating its current order belongs to a previous ID owner.
                        // Unknown chronology stays unbound for manual reconciliation.
                        DB::table('payment_proofs')->where('order_id', $order->id)
                            ->where('created_at', '>=', $order->created_at)
                            ->update(['order_receipt_key' => $key]);
                    }
                }
            });
        });
    }

    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropIndex(['order_receipt_key']);
            $table->dropColumn('order_receipt_key');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['receipt_key']);
            $table->dropColumn('receipt_key');
        });
    }
};
