<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DocumentType extends Model { protected $guarded=[]; protected $casts=['applicable_owner_types'=>'array','allowed_mime_types'=>'array','is_required'=>'boolean','is_expirable'=>'boolean','requires_verification'=>'boolean','is_active'=>'boolean']; public function category(){return $this->belongsTo(DocumentCategory::class,'document_category_id');}
public function requirements(){ return $this->hasMany(DocumentRequirement::class,'document_type_id'); }
public function activeRequirements(){ return $this->requirements()->where('is_active',true); }}
