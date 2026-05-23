<?php
namespace App\Models\Sales;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\SoftDeletes;
class Customer extends Model{use SoftDeletes;protected $guarded=[];public function sales(){return $this->hasMany(Sale::class);}public function invoices(){return $this->hasMany(CustomerInvoice::class);}public function payments(){return $this->hasMany(CustomerPayment::class);}public function priceList(){return $this->belongsTo(PriceList::class);} }
