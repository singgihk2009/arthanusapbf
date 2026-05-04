<?php

namespace App\Models\Procurement;

use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $guarded = [];

    protected $casts = [
        'received_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function items() { return $this->hasMany(GoodsReceiptItem::class); }
}
