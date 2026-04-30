<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ItemPicture extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'item_id',
        'disk',
        'path',
        'file_name',
        'mime_type',
        'size',
        'is_default',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'is_default' => 'bool',
            'size' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->path);
    }
}
