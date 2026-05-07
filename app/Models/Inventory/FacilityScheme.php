<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class FacilityScheme extends Model
{
    protected $fillable = ['code','name','description','is_active','is_restricted','requires_tracking','requires_reporting','requires_approval','tax_treatment','ownership_type','allowed_movement_types','metadata'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_restricted' => 'boolean',
            'requires_tracking' => 'boolean',
            'requires_reporting' => 'boolean',
            'requires_approval' => 'boolean',
            'allowed_movement_types' => 'array',
            'metadata' => 'array',
        ];
    }
}
