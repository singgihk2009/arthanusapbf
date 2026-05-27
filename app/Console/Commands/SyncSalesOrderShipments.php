<?php

namespace App\Console\Commands;

use App\Services\SalesOrderShipmentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSalesOrderShipments extends Command
{
    protected $signature = 'sales-orders:sync-shipments';
    protected $description = 'Repair posted sales-order dispatches that are not synced to sales lines.';

    public function handle(SalesOrderShipmentSyncService $syncService): int
    {
        $dispatches = DB::table('internal_usages')
            ->whereIn(DB::raw('LOWER(COALESCE(status, ""))'), ['posted', 'completed'])
            ->whereNull('sales_order_synced_at')
            ->where(function ($query) {
                $query->where('source_type', 'sales_order')->orWhereNotNull('sale_id');
            })
            ->orderBy('id')
            ->get();

        $synced = 0; $skipped = 0; $failed = 0;
        $this->info('Total found: '.$dispatches->count());

        foreach ($dispatches as $dispatch) {
            try {
                $result = $syncService->syncFromInternalUsage($dispatch->id, null);
                if ($result) {
                    $synced++;
                    $this->line("Synced {$dispatch->number}");
                } else {
                    $skipped++;
                    $this->warn("Skipped {$dispatch->number}");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Failed {$dispatch->number}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Synced: {$synced}");
        $this->warn("Skipped: {$skipped}");
        $this->error("Failed: {$failed}");

        return self::SUCCESS;
    }
}
