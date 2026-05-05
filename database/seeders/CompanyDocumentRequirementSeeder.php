<?php
namespace Database\Seeders;
use App\Models\DocumentRequirement;use App\Models\DocumentType;use Illuminate\Database\Seeder;
class CompanyDocumentRequirementSeeder extends Seeder {
    public function run(): void {
        $rows=[['AKTA_NOTARIS','Akta Notaris','legal',1,0,30,1],['NPWP','NPWP','tax',1,0,30,2],['NIB','NIB','regulatory',1,0,30,3],['IZIN_PBF','Izin PBF','regulatory',1,1,90,4],['IZIN_IDAK','Izin IDAK','regulatory',0,1,90,5],['CDOB_CERTIFICATE','Sertifikat CDOB','certification',1,1,90,6],['PKP','PKP','tax',0,0,30,7],['ISO_9001','ISO 9001','certification',0,1,60,8]];
        foreach($rows as [$code,$name,$cat,$req,$exp,$rem,$sort]){ $type=DocumentType::firstOrCreate(['code'=>$code],['name'=>$name,'category'=>$cat,'is_active'=>true]); DocumentRequirement::updateOrCreate(['business_id'=>null,'owner_type'=>'company','document_type_id'=>$type->id],['is_active'=>true,'is_required'=>(bool)$req,'is_expirable'=>(bool)$exp,'requires_verification'=>true,'reminder_days_before_expiry'=>$rem,'sort_order'=>$sort]); }
    }
}
