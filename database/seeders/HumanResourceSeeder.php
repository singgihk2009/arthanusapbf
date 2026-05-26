<?php
namespace Database\Seeders;
use App\Models\{Department,LicenseType,Position};use Illuminate\Database\Seeder;
class HumanResourceSeeder extends Seeder{public function run(): void{$cid=1;foreach(['SIPA','STRA','SIP Dokter','Sertifikat Kompetensi','CDOB','K3','ISO Internal Auditor'] as $n){LicenseType::updateOrCreate(['company_id'=>null,'code'=>strtoupper(str_replace(' ','_',$n))],['name'=>$n,'document_required'=>true,'expiry_required'=>true,'is_active'=>true]);}
foreach(['Management','Purchasing','Sales','Finance','Inventory','Warehouse','QA / Regulatory','Human Resource'] as $n){Department::updateOrCreate(['company_id'=>$cid,'name'=>$n],['is_active'=>true]);}
foreach(['Direktur','Manager','Staff Admin','Staff Purchasing','Staff Sales','Staff Warehouse','Apoteker Penanggung Jawab','Tenaga Teknis Kefarmasian','Finance Officer'] as $n){Position::updateOrCreate(['company_id'=>$cid,'name'=>$n],['is_active'=>true]);}}}
