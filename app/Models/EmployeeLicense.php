<?php
namespace App\Models;
use Carbon\Carbon;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class EmployeeLicense extends Model {use HasFactory; protected $fillable=['company_id','employee_id','license_type_id','license_number','issued_by','issued_date','expired_date','status','document_id','document_path','notes','is_primary']; protected $casts=['issued_date'=>'date','expired_date'=>'date','is_primary'=>'boolean'];
 public function employee(){return $this->belongsTo(Employee::class);} public function licenseType(){return $this->belongsTo(LicenseType::class);} public function getComputedStatusAttribute(){ if(in_array($this->status,['suspended','revoked'])) return $this->status; if(!$this->expired_date) return 'active'; $today=Carbon::today(); if($this->expired_date->lt($today)) return 'expired'; if($this->expired_date->lte($today->copy()->addDays(90))) return 'expiring_soon'; return 'active'; }}
