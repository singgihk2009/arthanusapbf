<?php
namespace App\Models\Regulatory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class RegulatorySource extends Model {
    protected $fillable=['source_name'];
    public function regulatoryProducts(): HasMany { return $this->hasMany(RegulatoryProduct::class,'source_id'); }
}
