<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Regulatory\RegulatoryProduct;
use App\Models\Regulatory\ProductAlias;

class Item extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'sku',
        'name',
        'nie',
        'product_type',
        'regulatory_category',
        'regulatory_source_id',
        'regulatory_product_id',
        'is_batch_tracked',
        'is_expiry_tracked',
        'requires_fefo',
        'category_id',
        'base_uom_id',
        'default_barcode',
        'track_expired',
        'is_active',
        'regulatory_name',
        'market_name',
        'dosage_form',
        'strength',
        'commodity_type',
        'manufacturer_name',
        'composition_text',
        'packing_text',
        'regulatory_class',
        'requires_batch_tracking',
        'requires_expiry_tracking',
    ];

    protected function casts(): array
    {
        return [
            'track_expired' => 'bool',
            'is_active' => 'bool',
            'requires_batch_tracking' => 'bool',
            'requires_expiry_tracking' => 'bool',
            'is_batch_tracked' => 'bool',
            'is_expiry_tracked' => 'bool',
            'requires_fefo' => 'bool',
        ];
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'base_uom_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ItemBarcode::class);
    }

    public function warehouseItemSettings(): HasMany
    {
        return $this->hasMany(WarehouseItemSetting::class);
    }

    public function pictures(): HasMany
    {
        return $this->hasMany(ItemPicture::class)->latest();
    }

    public function defaultPicture(): HasOne
    {
        return $this->hasOne(ItemPicture::class)->where('is_default', true);
    }
    public function regulatoryProducts(): BelongsToMany
    {
        return $this->belongsToMany(RegulatoryProduct::class, 'item_regulatory_products')->withPivot(['is_primary', 'notes', 'source_name', 'source_code'])->withTimestamps();
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }

    public function primaryRegulatoryProduct(): BelongsToMany
    {
        return $this->regulatoryProducts()->wherePivot('is_primary', true);
    }

}

