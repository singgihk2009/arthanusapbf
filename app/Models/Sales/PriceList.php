<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class PriceList extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'effective_from',
        'effective_to',
        'status',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_default' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PriceListLine::class);
    }

    public function isActiveForDate($date): bool
    {
        $date = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();

        if ($this->status !== 'active') {
            return false;
        }

        if ($this->effective_from && $date->lt($this->effective_from->startOfDay())) {
            return false;
        }

        if ($this->effective_to && $date->gt($this->effective_to->startOfDay())) {
            return false;
        }

        return true;
    }
}
