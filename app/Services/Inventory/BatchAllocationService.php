<?php

namespace App\Services\Inventory;

use App\Models\Inventory\StockBalance;

class BatchAllocationService
{
    /**
     * FEFO allocation from stock_balances for one item in one warehouse.
     *
     * @return array<int, array{batch_id:int|null, qty_base:float}>
     */
    public function allocateFefo(int $warehouseId, int $itemId, float $requiredQtyBase): array
    {
        $candidates = StockBalance::query()
            ->select('stock_balances.batch_id', 'stock_balances.on_hand_base')
            ->join('item_batches', 'item_batches.id', '=', 'stock_balances.batch_id')
            ->where('stock_balances.warehouse_id', $warehouseId)
            ->where('stock_balances.item_id', $itemId)
            ->where('stock_balances.on_hand_base', '>', 0)
            ->orderByRaw('item_batches.expired_date IS NULL, item_batches.expired_date ASC')
            ->orderBy('item_batches.id')
            ->get();

        $remaining = $requiredQtyBase;
        $allocations = [];

        foreach ($candidates as $candidate) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $candidate->on_hand_base;
            $take = min($available, $remaining);

            if ($take <= 0) {
                continue;
            }

            $allocations[] = [
                'batch_id' => $candidate->batch_id,
                'qty_base' => $take,
            ];

            $remaining -= $take;
        }

        if ($remaining > 0) {
            $allocations[] = [
                'batch_id' => null,
                'qty_base' => $remaining,
            ];
        }

        return $allocations;
    }
}
