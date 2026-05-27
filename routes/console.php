<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('sales-orders:sync-shipments {--dispatch-id=}', function () {
    $dispatchId = $this->option('dispatch-id');
    $query = DB::table('internal_usages')
        ->whereNull('sales_order_synced_at')
        ->where(function ($q): void {
            $q->where('source_type', 'sales_order')->orWhereNotNull('sale_id');
        })
        ->whereIn(DB::raw('LOWER(status)'), ['posted', 'completed'])
        ->orderBy('id');

    if (! empty($dispatchId)) {
        $query->where('id', (int) $dispatchId);
    }

    $dispatches = $query->get();
    if ($dispatches->isEmpty()) {
        $this->info('No unsynced posted sales-order dispatches found.');
        return;
    }

    $service = app(\App\Services\SalesOrderShipmentSyncService::class);
    $synced = 0; $skipped = 0; $failed = 0; $errors = [];

    foreach ($dispatches as $dispatch) {
        try {
            DB::transaction(function () use ($dispatch): void {
                $saleId = (int) ($dispatch->sale_id ?? $dispatch->source_id ?? 0);
                if ($saleId <= 0) {
                    return;
                }

                $lines = DB::table('internal_usage_lines')->where('internal_usage_id', $dispatch->id)->get();
                foreach ($lines as $line) {
                    if (! empty($line->sale_line_id)) {
                        continue;
                    }

                    $matches = DB::table('sales_lines')
                        ->where('sale_id', $saleId)
                        ->where('item_id', $line->item_id)
                        ->lockForUpdate()
                        ->get();

                    if ($matches->count() > 1) {
                        throw new RuntimeException('Cannot infer sale_line_id because multiple SO lines use same item.');
                    }

                    $match = $matches->first();
                    if (! $match) {
                        continue;
                    }

                    $lineQty = (float) ($line->qty ?? $line->quantity ?? $line->qty_used ?? $line->qty_out ?? $line->issued_qty ?? 0);
                    $remaining = (float) $match->qty_sold - (float) $match->qty_shipped;
                    if ($lineQty <= 0 || $lineQty - $remaining > 0.0001) {
                        throw new RuntimeException("Cannot infer sale_line_id for line {$line->id}: insufficient remaining qty.");
                    }

                    DB::table('internal_usage_lines')->where('id', $line->id)->update([
                        'sale_line_id' => $match->id,
                        'source_line_id' => $match->id,
                        'updated_at' => now(),
                    ]);
                }
            });

            $didSync = $service->syncFromInternalUsage((int) $dispatch->id);
            if ($didSync) {
                $synced++;
            } else {
                $skipped++;
            }
        } catch (\Throwable $e) {
            $failed++;
            $errors[] = "Dispatch #{$dispatch->id}: {$e->getMessage()}";
        }
    }

    $this->info("Synced: {$synced}");
    $this->warn("Skipped: {$skipped}");
    $this->error("Failed: {$failed}");
    foreach ($errors as $error) {
        $this->line($error);
    }
})->purpose('Backfill qty_shipped and sales status from posted dispatches linked to Sales Orders.');
