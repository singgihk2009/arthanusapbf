<?php
namespace App\Models\Sales;use App\Models\Item;use Illuminate\Database\Eloquent\Model;
class PriceListLine extends Model{protected $guarded=[];public function priceList(){return $this->belongsTo(PriceList::class);}public function item(){return $this->belongsTo(Item::class);} }
