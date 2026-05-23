<?php
namespace App\Models\Sales;use App\Models\Item;use Illuminate\Database\Eloquent\Model;
class ShipmentLine extends Model{protected $guarded=[];public function shipment(){return $this->belongsTo(Shipment::class);}public function saleLine(){return $this->belongsTo(SalesLine::class,'sale_line_id');}public function item(){return $this->belongsTo(Item::class);} }
