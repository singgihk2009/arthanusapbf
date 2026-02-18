<?php

namespace App\Listeners\Inventory;

use App\Events\Inventory\StockLedgerCreated;
use App\Models\Inventory\StockBalance;

class UpdateStockBalanceFromLedger
{
    public function handle(StockLedgerCreated $event): void
    {
        $ledger = $event->stockLedger;

        $balance = StockBalance::query()
            ->firstOrCreate(
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

        $balance->increment('on_hand_base', (float) $ledger->qty_base);
    }
}
