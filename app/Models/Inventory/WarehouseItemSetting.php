<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

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
}
