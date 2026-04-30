<?php

use App\Events\Inventory\StockLedgerCreated;
use App\Listeners\Inventory\UpdateStockBalanceFromLedger;
use App\Models\Inventory\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps stock balance idempotent when the same ledger event is handled more than once', function () {
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WH-SYNC',
        'name' => 'Warehouse Sync',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $uomId = DB::table('uoms')->insertGetId([
        'code' => 'PCS',
        'name' => 'Pieces',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $itemId = DB::table('items')->insertGetId([
        'sku' => 'ITEM-SYNC-01',
        'name' => 'Stock Sync Item',
        'base_uom_id' => $uomId,
        'track_expired' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ledger = StockLedger::query()->create([
        'trx_type' => 'OPENING_BALANCE',
        'trx_id' => 1,
        'warehouse_id' => $warehouseId,
        'item_id' => $itemId,
        'batch_id' => null,
        'qty_base' => 15,
        'uom_id' => $uomId,
        'qty_input' => 15,
        'trx_datetime' => now(),
    ]);

    $listener = new UpdateStockBalanceFromLedger;
    $event = new StockLedgerCreated($ledger);

    $listener->handle($event);
    $listener->handle($event);

    $onHandBase = (float) DB::table('stock_balances')
        ->where('warehouse_id', $warehouseId)
        ->where('item_id', $itemId)
        ->whereNull('batch_id')
        ->value('on_hand_base');

    expect($onHandBase)->toBe(15.0);
});
