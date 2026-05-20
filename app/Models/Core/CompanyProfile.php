<?php
namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $guarded = [];

    public function party(){ return $this->belongsTo(Party::class); }
}
