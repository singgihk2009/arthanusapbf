<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'base_uom_id',
        'default_barcode',
        'track_expired',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'track_expired' => 'bool',
            'is_active' => 'bool',
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
}
