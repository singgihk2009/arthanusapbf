<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class VendorInvoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'posted_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lines()
    {
        return $this->hasMany(VendorInvoiceLine::class);
    }
}
