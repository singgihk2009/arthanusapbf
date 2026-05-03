<?php
namespace App\Models\Core;
use Illuminate\Database\Eloquent\Model;

class Party extends Model
{
    protected $guarded = [];
    public function partyContacts(){ return $this->hasMany(PartyContact::class); }
    public function contacts(){ return $this->belongsToMany(Contact::class, 'party_contacts')->withPivot(['id','contact_role','is_primary','can_login','status','notes'])->withTimestamps(); }
}
