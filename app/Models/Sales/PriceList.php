<?php
namespace App\Models\Sales; use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\SoftDeletes;
class PriceList extends Model{use SoftDeletes;protected $guarded=[];public function lines(){return $this->hasMany(PriceListLine::class);} }
