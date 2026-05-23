<?php
namespace App\Models\Sales;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\SoftDeletes;
class CustomerInvoice extends Model{use SoftDeletes;protected $guarded=[];public function customer(){return $this->belongsTo(Customer::class);}public function sale(){return $this->belongsTo(Sale::class);}public function shipment(){return $this->belongsTo(Shipment::class);}public function lines(){return $this->hasMany(CustomerInvoiceLine::class);}public function allocations(){return $this->hasMany(CustomerPaymentAllocation::class);} }
