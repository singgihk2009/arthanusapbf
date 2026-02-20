<?php

namespace App\Listeners\Inventory;

use App\Events\Inventory\StockLedgerCreated;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockLedger;

class UpdateStockBalanceFromLedger
{
    public function handle(StockLedgerCreated $event): void
    {
        $ledger = $event->stockLedger;

        $balance = StockBalance::query()->firstOrCreate(
            [
                'warehouse_id' => $ledger->warehouse_id,
                'item_id' => $ledger->item_id,
                'batch_id' => $ledger->batch_id,
            ],
            [
                'on_hand_base' => 0,
                'reserved_base' => 0,
            ]
        );

        $onHandBase = (float) StockLedger::query()
            ->where('warehouse_id', $ledger->warehouse_id)
            ->where('item_id', $ledger->item_id)
            ->where('batch_id', $ledger->batch_id)
            ->sum('qty_base');

        $balance->update(['on_hand_base' => $onHandBase]);
    }
}
