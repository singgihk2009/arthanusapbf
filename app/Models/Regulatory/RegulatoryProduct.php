<?php
namespace App\Models\Regulatory;
use App\Models\Inventory\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class RegulatoryProduct extends Model {
    protected $fillable=['source_id','nie','source_code','product_name_source','industry_name','dosage_form','strength','commodity_type','raw_packaging_text','raw_composition_text','raw_payload'];
    protected function casts(): array { return ['raw_payload'=>'array']; }
    public function source(): BelongsTo { return $this->belongsTo(RegulatorySource::class,'source_id'); }
    public function compositions(): HasMany { return $this->hasMany(ProductComposition::class); }
    public function packagings(): HasMany { return $this->hasMany(ProductPackaging::class); }
    public function items(): BelongsToMany { return $this->belongsToMany(Item::class,'item_regulatory_products')->withPivot(['is_primary','notes','source_name','source_code'])->withTimestamps(); }
}
