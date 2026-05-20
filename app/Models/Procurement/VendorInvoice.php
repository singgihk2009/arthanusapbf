<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class VendorInvoice extends Model
{
    protected $guarded = [];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lines()
    {
        return $this->hasMany(VendorInvoiceLine::class);
    }
}
