<?php
namespace App\Models\Procurement;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrder extends Model { protected $guarded=[]; public function items(){return $this->hasMany(PurchaseOrderItem::class,'purchase_order_id');} public function vendor(){return $this->belongsTo(Vendor::class);} public function goodsReceipts(){return $this->hasMany(GoodsReceipt::class,'purchase_order_id');}}
