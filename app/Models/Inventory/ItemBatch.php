<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class ItemBatch extends Model
{
    protected $fillable = [
        'item_id',
        'batch_no',
        'expired_date',
    ];

    protected function casts(): array
    {
        return [
            'expired_date' => 'date',
        ];
    }
}
