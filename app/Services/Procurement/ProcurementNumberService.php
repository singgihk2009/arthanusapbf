<?php
namespace App\Services\Procurement;
use Illuminate\Support\Facades\DB;
class ProcurementNumberService { public function generate(string $prefix,string $table,string $field):string{ $ym=now()->format('Ym'); $last=DB::table($table)->where($field,'like',"$prefix-$ym-%")->max($field); $seq=$last?((int)substr($last,-4))+1:1; return sprintf('%s-%s-%04d',$prefix,$ym,$seq);} }
