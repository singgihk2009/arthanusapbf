<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseItemSetting extends Model
{
    protected $fillable = [
        'warehouse_id',
        'item_id',
        'min_stock_base',
    ];

    protected function casts(): array
    {
        return [
            'min_stock_base' => 'decimal:6',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
