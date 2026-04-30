<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\ItemUomConversion;
use InvalidArgumentException;

class UomConversionService
{
    public function toBase(int $itemId, int $uomId, float $qty): float
    {
        $item = Item::query()->findOrFail($itemId);

        if ($uomId === (int) $item->base_uom_id) {
            return $qty;
        }

        $conversion = ItemUomConversion::query()
            ->where('item_id', $itemId)
            ->where('from_uom_id', $uomId)
            ->where('to_uom_id', $item->base_uom_id)
            ->first();

        if ($conversion) {
            return $qty * (float) $conversion->factor;
        }

        $reverseConversion = ItemUomConversion::query()
            ->where('item_id', $itemId)
            ->where('from_uom_id', $item->base_uom_id)
            ->where('to_uom_id', $uomId)
            ->first();

        if ($reverseConversion) {
            return $qty / (float) $reverseConversion->factor;
        }

        throw new InvalidArgumentException('UOM conversion to base is not configured for this item.');
    }
}
