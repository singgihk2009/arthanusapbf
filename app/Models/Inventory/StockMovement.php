<?php

namespace App\Models\Inventory;

use App\Models\Inventory\Item;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $guarded = [];

    public function product() { return $this->belongsTo(Item::class, 'product_id'); }
}
