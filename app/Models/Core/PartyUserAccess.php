<?php
namespace App\Models\Core;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
class PartyUserAccess extends Model
{
    protected $table='party_user_access';
    protected $guarded=[];
    protected $casts=['is_default'=>'boolean'];
    public function user(){ return $this->belongsTo(User::class); }
    public function party(){ return $this->belongsTo(Party::class); }
}
