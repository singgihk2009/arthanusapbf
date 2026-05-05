<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DocumentAuditLog extends Model { public $timestamps=false; protected $guarded=[]; protected $casts=['old_values'=>'array','new_values'=>'array','performed_at'=>'datetime']; public function document(){return $this->belongsTo(Document::class);} }
