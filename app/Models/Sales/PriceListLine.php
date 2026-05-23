<?php

namespace App\Models\Sales;

use App\Models\Inventory\Item;
use App\Models\Inventory\Uom;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListLine extends Model
{
    protected $fillable = [
        'price_list_id',
        'item_id',
        'uom_id',
        'min_qty',
        'price',
        'discount_percent',
        'tax_included',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:4',
            'price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'tax_included' => 'boolean',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
