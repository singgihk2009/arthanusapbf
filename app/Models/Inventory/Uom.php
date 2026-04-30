<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Uom extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'code',
        'name',
    ];
}
