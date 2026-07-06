<?php

namespace App\Models\Procurement;

use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'return_date' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function lines() { return $this->hasMany(PurchaseReturnLine::class); }
    public function deduction() { return $this->hasOne(VendorInvoiceDeduction::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
