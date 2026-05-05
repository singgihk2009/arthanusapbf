<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
