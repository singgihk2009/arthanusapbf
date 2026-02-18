<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
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
}
