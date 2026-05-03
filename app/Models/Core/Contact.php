<?php
namespace App\Models\Core;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function partyContacts(){ return $this->hasMany(PartyContact::class); }
    public function parties(){ return $this->belongsToMany(Party::class, 'party_contacts')->withPivot(['id','contact_role','is_primary','can_login','status','notes'])->withTimestamps(); }
    public function user(){ return $this->hasOne(User::class); }
}
