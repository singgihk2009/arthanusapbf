<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockLedger extends Model
{
    protected $fillable = [
        'trx_type',
        'trx_id',
        'trx_line_id',
        'warehouse_id',
        'item_id',
        'batch_id',
        'facility_scheme_id',
        'qty_base',
        'uom_id',
        'qty_input',
        'unit_cost',
        'trx_datetime',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:6',
            'qty_input' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'trx_datetime' => 'datetime',
        ];
    }
}
