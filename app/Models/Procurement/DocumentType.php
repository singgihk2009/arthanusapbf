<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentType extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
        'is_critical' => 'boolean',
        'blocks_transaction' => 'boolean',
        'requires_expiry_date' => 'boolean',
        'is_active' => 'boolean',
    ];
}
