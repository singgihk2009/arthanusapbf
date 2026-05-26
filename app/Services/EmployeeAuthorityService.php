<?php
namespace App\Services;
use App\Models\Employee;use App\Models\EmployeeLicense;use App\Models\User;
class EmployeeAuthorityService {
 public function getCurrentEmployee(): ?Employee {return auth()->user()?->employee?->load(['position','department']);}
 public function getActiveLicenses($employeeId){return EmployeeLicense::where('employee_id',$employeeId)->get()->filter(fn($l)=>$l->computed_status==='active')->values();}
 public function hasActiveLicense($employeeId,$licenseCode): bool {return $this->getPrimaryLicense($employeeId,$licenseCode)!==null;}
 public function getPrimaryLicense($employeeId,$licenseCode=null): ?EmployeeLicense {$q=EmployeeLicense::with('licenseType')->where('employee_id',$employeeId)->where('is_primary',true);if($licenseCode)$q->whereHas('licenseType',fn($x)=>$x->where('code',$licenseCode));$l=$q->first();if(!$l||$l->computed_status!=='active')return null;return $l;}
 public function getDocumentSignatureProfile($userId): array {$u=User::with('employee.position','employee.department')->find($userId);$e=$u?->employee;$p=$e?->licenses()->with('licenseType')->where('is_primary',true)->first();return ['employee_name'=>$e?->full_name,'position_name'=>$e?->position?->name,'department_name'=>$e?->department?->name,'primary_license_type'=>$p?->licenseType?->name,'primary_license_number'=>$p?->license_number,'primary_license_expired_date'=>$p?->expired_date?->toDateString(),'signature_path'=>$e?->signature_path];}
}
