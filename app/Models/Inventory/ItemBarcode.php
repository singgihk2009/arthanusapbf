<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemBarcode extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'item_id',
        'barcode',
        'note',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
