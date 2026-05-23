<?php
namespace App\Models\Sales;use Illuminate\Database\Eloquent\Model;
class CustomerPaymentAllocation extends Model{protected $guarded=[];public function payment(){return $this->belongsTo(CustomerPayment::class,'customer_payment_id');}public function invoice(){return $this->belongsTo(CustomerInvoice::class,'customer_invoice_id');}}
