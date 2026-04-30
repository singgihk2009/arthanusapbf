<?php

namespace App\Listeners\Inventory;

use App\Events\Inventory\StockLedgerCreated;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

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

        $this->syncInvBalances($ledger);
    }

    private function syncInvBalances(StockLedger $ledger): void
    {
        $itemId = (int) $ledger->item_id;
        $warehouseId = (int) $ledger->warehouse_id;
        $qtyDelta = (float) $ledger->qty_base;

        if ($qtyDelta === 0.0) {
            return;
        }

        $balance = DB::table('inv_balances')
            ->where('company_id', 1)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $itemId)
            ->first();

        $onHand = (float) ($balance->on_hand_qty ?? 0);
        $stockValue = (float) ($balance->stock_value ?? 0);
        $currentAvg = (float) ($balance->avg_cost ?? 0);
        $inputUnitCost = (float) ($ledger->unit_cost ?? 0);

        if ($qtyDelta > 0) {
            $effectiveCost = $inputUnitCost > 0 ? $inputUnitCost : $currentAvg;
            $newOnHand = $onHand + $qtyDelta;
            $newStockValue = $stockValue + ($qtyDelta * $effectiveCost);
        } else {
            $issuedQty = abs($qtyDelta);
            $effectiveCost = $inputUnitCost > 0 ? $inputUnitCost : $currentAvg;
            $newOnHand = max(0, $onHand - $issuedQty);
            $newStockValue = max(0, $stockValue - ($issuedQty * $effectiveCost));
        }

        $avg = $newOnHand > 0 ? ($newStockValue / $newOnHand) : 0;

        DB::table('inv_balances')->updateOrInsert(
            ['company_id' => 1, 'warehouse_id' => $warehouseId, 'product_id' => $itemId],
            [
                'on_hand_qty' => $newOnHand,
                'avg_cost' => $avg,
                'stock_value' => $newStockValue,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (! $ledger->batch_id) {
            return;
        }

        $batch = DB::table('item_batches')->where('id', $ledger->batch_id)->first(['batch_no', 'expired_date']);
        if (! $batch?->batch_no) {
            return;
        }

        $invBatch = DB::table('inv_batches')
            ->where('company_id', 1)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $itemId)
            ->where('batch_no', $batch->batch_no)
            ->first();

        $batchQtyOnHand = (float) ($invBatch->qty_on_hand ?? 0);
        $batchStockValue = (float) ($invBatch->stock_value ?? 0);
        $batchCurrentCost = (float) ($invBatch->unit_cost ?? 0);
        $batchEffectiveCost = $inputUnitCost > 0 ? $inputUnitCost : ($batchCurrentCost > 0 ? $batchCurrentCost : $currentAvg);

        if ($qtyDelta > 0) {
            $newBatchQty = $batchQtyOnHand + $qtyDelta;
            $newBatchValue = $batchStockValue + ($qtyDelta * $batchEffectiveCost);
        } else {
            $issuedQty = abs($qtyDelta);
            $newBatchQty = max(0, $batchQtyOnHand - $issuedQty);
            $newBatchValue = max(0, $batchStockValue - ($issuedQty * $batchEffectiveCost));
        }

        DB::table('inv_batches')->updateOrInsert(
            ['company_id' => 1, 'warehouse_id' => $warehouseId, 'product_id' => $itemId, 'batch_no' => $batch->batch_no],
            [
                'expired_date' => $batch->expired_date,
                'unit_cost' => $batchEffectiveCost,
                'qty_on_hand' => $newBatchQty,
                'stock_value' => $newBatchValue,
                'status' => $newBatchQty > 0 ? 'active' : 'depleted',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
