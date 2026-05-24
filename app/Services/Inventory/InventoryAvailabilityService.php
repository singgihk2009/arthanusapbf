<?php

namespace App\Services\Inventory;

use App\Models\Inventory\StockBalance;

class InventoryAvailabilityService
{
    public function getAvailableStock(int $itemId, ?int $warehouseId = null): ?float
    {
        if (! class_exists(StockBalance::class)) {
            return null;
        }

        $query = StockBalance::query()->where('item_id', $itemId);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stock = $query->selectRaw('COALESCE(SUM(on_hand_base),0) as on_hand')->value('on_hand');

        return $stock === null ? null : (float) $stock;
    }

    public function stockStatus(?float $availableStock, ?float $requestedQty = null): string
    {
        if ($availableStock === null) {
            return 'unknown';
        }

        if ($availableStock <= 0) {
            return 'out_of_stock';
        }

        if ($requestedQty !== null && $requestedQty > $availableStock) {
            return 'out_of_stock';
        }

        if ($availableStock < 5) {
            return 'low_stock';
        }

        return 'in_stock';
    }
}
