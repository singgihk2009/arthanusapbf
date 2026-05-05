<?php
namespace App\Console\Commands;
use App\Models\DocumentRequirement;use App\Models\DocumentType;use Illuminate\Console\Command;
class MigrateDocumentTypeRequirements extends Command {
protected $signature='documents:migrate-type-requirements'; protected $description='Migrate document_types defaults into document_requirements';
public function handle(){ $c=$u=$s=$f=0; DocumentType::chunkById(100,function($types) use (&$c,&$u,&$s,&$f){ foreach($types as $t){ try{ $owners=$t->applicable_owner_types; if(!is_array($owners)||empty($owners))$owners=['vendor']; foreach($owners as $owner){ $payload=['is_active'=>true,'is_required'=>(bool)$t->is_required,'is_expirable'=>(bool)$t->is_expirable,'requires_verification'=>(bool)$t->requires_verification,'sort_order'=>(int)($t->sort_order ?? 0)]; $row=DocumentRequirement::where('business_id',$t->business_id)->where('owner_type',$owner)->where('document_type_id',$t->id)->first(); if(!$row){ DocumentRequirement::create(array_merge($payload,['business_id'=>$t->business_id,'owner_type'=>$owner,'document_type_id'=>$t->id])); $c++; } else { $changed=collect($payload)->some(fn($v,$k)=>$row->{$k}!=$v); if($changed){$row->update($payload);$u++;}else{$s++;} } } }catch(\Throwable $e){$f++;} }}); $this->info("created=$c updated=$u skipped=$s failed=$f"); return self::SUCCESS; }
}
