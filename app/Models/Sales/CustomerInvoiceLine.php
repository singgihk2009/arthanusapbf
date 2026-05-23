<?php
namespace App\Models\Sales;use Illuminate\Database\Eloquent\Model;
class CustomerInvoiceLine extends Model{protected $guarded=[];public function invoice(){return $this->belongsTo(CustomerInvoice::class,'customer_invoice_id');}}
