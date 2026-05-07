<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockBalance extends Model
{
    protected $fillable = [
        'warehouse_id',
        'item_id',
        'batch_id',
        'facility_scheme_id',
        'on_hand_base',
        'reserved_base',
    ];

    protected function casts(): array
    {
        return [
            'on_hand_base' => 'decimal:6',
            'reserved_base' => 'decimal:6',
        ];
    }
}
