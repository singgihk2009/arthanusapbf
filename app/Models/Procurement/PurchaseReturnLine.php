<?php

namespace App\Models\Procurement;

use App\Models\Inventory\Item;
use App\Models\Inventory\Uom;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expired_date' => 'date:Y-m-d',
    ];

    public function purchaseReturn() { return $this->belongsTo(PurchaseReturn::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function uom() { return $this->belongsTo(Uom::class); }
}
