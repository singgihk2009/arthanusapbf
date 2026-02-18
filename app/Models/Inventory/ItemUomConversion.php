<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class ItemUomConversion extends Model
{
    protected $fillable = [
        'item_id',
        'from_uom_id',
        'to_uom_id',
        'factor',
    ];
}
