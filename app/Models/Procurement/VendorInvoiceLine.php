<?php

namespace App\Models\Procurement;

use App\Models\Inventory\Item;
use Illuminate\Database\Eloquent\Model;

class VendorInvoiceLine extends Model
{
    protected $guarded = [];

    public function vendorInvoice()
    {
        return $this->belongsTo(VendorInvoice::class);
    }

    public function receivingLine()
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'receipt_line_id');
    }

    public function purchaseOrderLine()
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
