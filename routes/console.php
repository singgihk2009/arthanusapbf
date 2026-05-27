<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('sales-orders:sync-dispatches {--dispatch-id=} {--dry-run}', function () {
    $dispatchId = $this->option('dispatch-id');

    $query = DB::table('internal_usages')
        ->where('status', 'POSTED')
        ->where('source_type', 'sales_order')
        ->whereNull('sales_order_synced_at')
        ->orderBy('id');

    if (! empty($dispatchId)) {
        $query->where('id', (int) $dispatchId);
    }

    $dispatches = $query->pluck('id');

    if ($dispatches->isEmpty()) {
        $this->info('No unsynced posted sales-order dispatches found.');

        return;
    }

    $this->line('Found '.$dispatches->count().' dispatch(es): '.$dispatches->implode(', '));

    if ($this->option('dry-run')) {
        $this->comment('Dry run enabled, no changes were made.');

        return;
    }

    $syncService = app(\App\Services\SalesOrderShipmentSyncService::class);

    foreach ($dispatches as $id) {
        $syncService->syncFromDispatch((int) $id);
        $this->info('Synced dispatch #'.$id);
    }

    $this->info('Sales Order dispatch sync completed successfully.');
})->purpose('Backfill qty_shipped and sales status from posted dispatches linked to Sales Orders.');
