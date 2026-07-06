<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class VendorInvoiceDeduction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'deduction_date' => 'date:Y-m-d',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function vendorInvoice() { return $this->belongsTo(VendorInvoice::class); }
    public function purchaseReturn() { return $this->belongsTo(PurchaseReturn::class); }
}
