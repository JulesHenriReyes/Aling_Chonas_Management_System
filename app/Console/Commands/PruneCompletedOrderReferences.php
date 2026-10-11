<?php

namespace App\Console\Commands;

use App\Services\CompletedOrderRetentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneCompletedOrderReferences extends Command
{
    protected $signature = 'orders:prune-references {--apply : Delete eligible reference photos and expire history}';
    protected $description = 'Preview or apply retention for completed orders only (60-day photos, 365-day history)';

    public function handle(CompletedOrderRetentionService $retention): int
    {
        $result = $retention->run((bool) $this->option('apply'));
        if ($result['already_running']) {
            $this->info('Another order retention run is active; skipped.');
            return self::SUCCESS;
        }
        $this->info($result['apply'] ? 'Completed-order cleanup applied.' : 'Preview only. No photos or history were removed.');
        $this->table(['Orders checked', 'Photos expired (orders)', 'Histories expired', 'Files', 'Bytes', 'Protected files', 'Skipped orders'],
            [[$result['orders_checked'], $result['photos_expired'], $result['histories_expired'],
                $result['files'], $result['bytes'], $result['protected_files'], $result['skipped_orders']]]);
        foreach ($result['errors'] as $error) $this->warn($error);
        if ($result['apply']) Log::info('Completed order reference retention', $result);
        return $result['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
