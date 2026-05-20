<?php

namespace App\Models\Procurement;

use App\Models\Inventory\Item;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptLine extends Model
{
    protected $guarded = [];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
