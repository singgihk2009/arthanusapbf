<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyType extends Model
{
    protected $fillable = ['category', 'code', 'name', 'prefix', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
