<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['issue_date'=>'date','expiry_date'=>'date','verified_at'=>'datetime','metadata'=>'array','is_current'=>'boolean'];
    public function category(){ return $this->belongsTo(DocumentCategory::class,'document_category_id'); }
    public function type(){ return $this->belongsTo(DocumentType::class,'document_type_id'); }
    public function documentType(){ return $this->belongsTo(DocumentType::class,'document_type_id'); }
    public function uploadedBy(){ return $this->belongsTo(User::class,'uploaded_by'); }
    public function verifiedBy(){ return $this->belongsTo(User::class,'verified_by'); }
    public function auditLogs(){ return $this->hasMany(DocumentAuditLog::class); }

    public function parent(){ return $this->belongsTo(Document::class,'parent_document_id'); }
    public function children(){ return $this->hasMany(Document::class,'parent_document_id'); }
    public function replacedBy(){ return $this->belongsTo(Document::class,'replaced_by_document_id'); }
    public function replaces(){ return $this->hasOne(Document::class,'replaced_by_document_id'); }
    public function versions(){ return static::query()->where(function($q){ $root=$this->getRootDocumentId(); $q->where('id',$root)->orWhere('parent_document_id',$root); }); }
    public function getRootDocumentId(): int { return (int) ($this->parent_document_id ?: $this->id); }
    public function getNextVersionNumber(): int { return (int) static::query()->where(function($q){$root=$this->getRootDocumentId();$q->where('id',$root)->orWhere('parent_document_id',$root);})->max('version_number') + 1; }
    public function markAsNotCurrent(): void { $this->update(['is_current'=>false]); }
    public function canUploadRevision(): bool { return $this->status === 'rejected'; }
    public function canUploadRenewal(): bool { return $this->status === 'expired' || ($this->status === 'verified' && $this->expiry_date && $this->expiry_date->lte(now()->addDays(30))); }
    public function isCompliant(): bool { return $this->is_current && $this->status === 'verified' && (!$this->expiry_date || $this->expiry_date->gte(now()->toDateString())); }
    public function isExpired(): bool { return $this->status === 'expired' || ($this->expiry_date && $this->expiry_date->lt(now()->toDateString())); }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function scopeForOwner($q,string $t,int $id){ return $q->where('owner_type',$t)->where('owner_id',$id); }
    public function scopeForDocumentType($q,int $documentTypeId){ return $q->where('document_type_id',$documentTypeId); }
    public function scopeCurrent($q){ return $q->where('is_current', true); }
    public function scopeHistorical($q){ return $q->where('is_current', false); }
    public function scopeCompliant($q){ return $q->current()->where('status','verified')->where(function($w){$w->whereNull('expiry_date')->orWhereDate('expiry_date','>=',now()->toDateString());}); }
    public function scopeNonCompliant($q){ return $q->where(function($w){$w->where('is_current',false)->orWhere('status','!=','verified')->orWhereDate('expiry_date','<',now()->toDateString());}); }
    public function scopeExpiringSoon($q,int $days=30){ return $q->whereDate('expiry_date','>=',now()->toDateString())->whereDate('expiry_date','<=',now()->addDays($days)->toDateString()); }
    public function scopeExpired($q){ return $q->whereDate('expiry_date','<',now()->toDateString())->where('status','!=','archived'); }
    public function scopePendingReview($q){ return $q->where('status','pending_review'); }
    public function scopeVerified($q){ return $q->where('status','verified'); }
    public function scopeActive($q){ return $q->whereNull('deleted_at')->where('status','!=','archived'); }
}

