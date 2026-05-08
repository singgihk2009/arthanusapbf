<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address',
        'is_active',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_warehouses')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }
}
