<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class Uom extends Model
{
    protected $fillable = [
        'code',
        'name',
    ];
}
