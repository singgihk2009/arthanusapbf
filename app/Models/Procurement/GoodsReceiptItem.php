<?php

namespace App\Models\Procurement;

use App\Models\Inventory\Item;
use App\Models\Inventory\Uom;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $guarded = [];

    public function goodsReceipt() { return $this->belongsTo(GoodsReceipt::class); }
    public function purchaseOrderItem() { return $this->belongsTo(PurchaseOrderItem::class); }
    public function product() { return $this->belongsTo(Item::class, 'product_id'); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function uom() { return $this->belongsTo(Uom::class); }
}
