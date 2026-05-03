<?php

namespace App\Models\Procurement;

use App\Models\Inventory\Item;
use App\Models\Inventory\Uom;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';
    protected $guarded = [];

    public function purchaseOrder(){ return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function product(){ return $this->belongsTo(Item::class, 'product_id'); }
    public function uom(){ return $this->belongsTo(Uom::class, 'uom_id'); }
}
