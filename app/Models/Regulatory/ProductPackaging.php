<?php
namespace App\Models\Regulatory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductPackaging extends Model {
    protected $fillable=['regulatory_product_id','packaging_type','outer_qty','outer_unit','inner_qty','inner_unit','content_qty','content_unit','description_raw'];
    public function regulatoryProduct(): BelongsTo { return $this->belongsTo(RegulatoryProduct::class); }
}
