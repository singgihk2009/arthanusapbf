<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyModule extends Model
{
    protected $fillable = ['company_id', 'module_code', 'is_enabled', 'settings_json'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings_json' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_code', 'code');
    }
}
