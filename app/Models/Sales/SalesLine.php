<?php
namespace App\Models\Sales; use App\Models\Item;use Illuminate\Database\Eloquent\Model;
class SalesLine extends Model{protected $table='sales_lines';protected $guarded=[];public function sale(){return $this->belongsTo(Sale::class);}public function item(){return $this->belongsTo(Item::class);}public function shipmentLines(){return $this->hasMany(ShipmentLine::class,'sale_line_id');}public function invoiceLines(){return $this->hasMany(CustomerInvoiceLine::class,'sale_line_id');}}
