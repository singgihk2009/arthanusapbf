<?php
namespace App\Models\Sales; use App\Models\Warehouse;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\SoftDeletes;
class Sale extends Model{use SoftDeletes;protected $guarded=[];public function customer(){return $this->belongsTo(Customer::class);}public function warehouse(){return $this->belongsTo(Warehouse::class);}public function lines(){return $this->hasMany(SalesLine::class);}public function shipments(){return $this->hasMany(Shipment::class);}public function invoices(){return $this->hasMany(CustomerInvoice::class);} }
