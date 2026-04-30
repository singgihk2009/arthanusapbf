<?php
namespace App\Models\Regulatory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductComposition extends Model {
    protected $fillable=['regulatory_product_id','substance_name','strength','unit'];
    public function regulatoryProduct(): BelongsTo { return $this->belongsTo(RegulatoryProduct::class); }
}
