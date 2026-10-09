<?php

namespace App\Console\Commands;

use App\Models\StockEntry;
use App\Models\Supply;
use Illuminate\Console\Command;

class AuditInventoryReconciliation extends Command
{
    protected $signature = 'inventory:audit';

    protected $description = 'Audit inventory supplies against remaining quantities in stock_entries batches';

    public function handle(): int
    {
        $supplies = Supply::orderBy('id')->get();
        $discrepancies = 0;
        $rows = [];

        foreach ($supplies as $supply) {
            $batchTotal = (float) StockEntry::where('supply_id', $supply->id)->sum('remaining_quantity');
            $current = (float) $supply->current_quantity;
            $diff = round($current - $batchTotal, 4);

            if (abs($diff) > 0.0001) {
                $discrepancies++;
            }

            $rows[] = [
                $supply->id,
                $supply->supply_name,
                $supply->unit,
                number_format($current, 2),
                number_format($batchTotal, 2),
                $diff === 0.0 ? 'OK' : number_format($diff, 2),
            ];
        }

        $this->table(['ID', 'Supply Name', 'Unit', 'Current Qty', 'Batch Total', 'Status/Diff'], $rows);

        if ($discrepancies === 0) {
            $this->info("All {$supplies->count()} supplies match stock entries (0 discrepancies).");
            return self::SUCCESS;
        }

        $this->error("Found {$discrepancies} supply discrepancies between current_quantity and stock_entries!");
        return self::FAILURE;
    }
}
