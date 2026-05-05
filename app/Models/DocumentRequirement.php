<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class DocumentRequirement extends Model {
    use SoftDeletes;
    protected $guarded=[];
    protected $casts=['is_active'=>'boolean','is_required'=>'boolean','is_expirable'=>'boolean','requires_verification'=>'boolean'];
    public function business(){ return class_exists(\App\Models\Business::class) ? $this->belongsTo(\App\Models\Business::class) : null; }
    public function documentType(){ return $this->belongsTo(DocumentType::class,'document_type_id'); }
    public function scopeForOwnerType($q,string $ownerType){ return $q->where('owner_type',$ownerType); }
    public function scopeActive($q){ return $q->where('is_active',true); }
    public function scopeRequired($q){ return $q->where('is_required',true); }
    public function scopeExpirable($q){ return $q->where('is_expirable',true); }
    public function scopeForBusiness($q,$businessId){ return $businessId===null ? $q->whereNull('business_id') : $q->where('business_id',$businessId); }
}
