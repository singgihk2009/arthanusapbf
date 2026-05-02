<?php
namespace App\Models\Procurement;
use Illuminate\Database\Eloquent\Model;
class VendorPayment extends Model { protected $guarded=[]; public function allocations(){return $this->hasMany(VendorPaymentAllocation::class);} }
