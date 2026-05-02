<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorDocumentRequirement extends Model
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

    public function documentType(){ return $this->belongsTo(DocumentType::class); }
}
