<?php
namespace App\Models\Regulatory;
use Illuminate\Database\Eloquent\Model;
class ItemRegulatoryProduct extends Model { protected $fillable=['item_id','regulatory_product_id','is_primary','notes','source_name','source_code']; protected function casts(): array { return ['is_primary'=>'bool']; }}
