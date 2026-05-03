<?php
namespace App\Models\Core;
use Illuminate\Database\Eloquent\Model;
class PartyContact extends Model
{
    protected $guarded=[];
    protected $casts=['is_primary'=>'boolean','can_login'=>'boolean'];
    public function party(){ return $this->belongsTo(Party::class); }
    public function contact(){ return $this->belongsTo(Contact::class); }
}
