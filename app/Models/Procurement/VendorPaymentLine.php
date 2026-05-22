<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPaymentLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date',
    ];

    public function payment(): BelongsTo { return $this->belongsTo(VendorPayment::class, 'vendor_payment_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(VendorInvoice::class, 'vendor_invoice_id'); }
}
