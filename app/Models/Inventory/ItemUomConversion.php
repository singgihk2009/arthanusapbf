<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemUomConversion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'item_id',
        'from_uom_id',
        'to_uom_id',
        'factor',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fromUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'from_uom_id');
    }

    public function toUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'to_uom_id');
    }
}
