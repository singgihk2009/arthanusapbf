<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['issue_date'=>'date','expiry_date'=>'date','verified_at'=>'datetime','metadata'=>'array'];
    public function category(){ return $this->belongsTo(DocumentCategory::class,'document_category_id'); }
    public function type(){ return $this->belongsTo(DocumentType::class,'document_type_id'); }
    public function documentType(){ return $this->belongsTo(DocumentType::class,'document_type_id'); }
    public function uploadedBy(){ return $this->belongsTo(User::class,'uploaded_by'); }
    public function verifiedBy(){ return $this->belongsTo(User::class,'verified_by'); }
    public function auditLogs(){ return $this->hasMany(DocumentAuditLog::class); }
    public function scopeForOwner($q,string $t,int $id){ return $q->where('owner_type',$t)->where('owner_id',$id); }
    public function scopeExpiringSoon($q,int $days=30){ return $q->whereDate('expiry_date','>=',now()->toDateString())->whereDate('expiry_date','<=',now()->addDays($days)->toDateString()); }
    public function scopeExpired($q){ return $q->whereDate('expiry_date','<',now()->toDateString())->where('status','!=','archived'); }
    public function scopePendingReview($q){ return $q->where('status','pending_review'); }
    public function scopeVerified($q){ return $q->where('status','verified'); }
    public function scopeActive($q){ return $q->whereNull('deleted_at')->where('status','!=','archived'); }
}
