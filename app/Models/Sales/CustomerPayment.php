<?php
namespace App\Models\Sales;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\SoftDeletes;
class CustomerPayment extends Model{use SoftDeletes;protected $guarded=[];public function customer(){return $this->belongsTo(Customer::class);}public function allocations(){return $this->hasMany(CustomerPaymentAllocation::class);} }
