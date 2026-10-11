<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\CompletedOrderRetentionService;
use Illuminate\Console\Command;

class HoldOrderReferences extends Command
{
    protected $signature = 'orders:reference-hold {order : Exact order number} {--reason= : Reason for keeping the photos/history} {--release : Release an existing hold}';
    protected $description = 'Protect an order from reference cleanup while a complaint or other review remains open';

    public function handle(CompletedOrderRetentionService $retention): int
    {
        $order = Order::where('order_number', $this->argument('order'))->first();
        if (! $order) {
            $this->error('Order not found.');
            return self::FAILURE;
        }
        $reason = trim((string) $this->option('reason'));
        if (! $this->option('release') && ($reason === '' || mb_strlen($reason) > 1000)) {
            $this->error('Provide --reason with 1 to 1000 characters.');
            return self::FAILURE;
        }
        try {
            $retention->setHold($order, $this->option('release') ? null : $reason);
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
        $this->info($this->option('release') ? 'Retention hold released.' : 'Photos and design history are protected from cleanup.');
        return self::SUCCESS;
    }
}
