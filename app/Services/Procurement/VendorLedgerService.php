<?php
namespace App\Services\Procurement;
use App\Models\Procurement\VendorLedger;
class VendorLedgerService { public function post(int $vendorId,string $type,int $id,string $desc,float $debit,float $credit):void{$bal=(float)(VendorLedger::where('vendor_id',$vendorId)->latest('id')->value('balance')??0); VendorLedger::create(['vendor_id'=>$vendorId,'transaction_date'=>now()->toDateString(),'reference_type'=>$type,'reference_id'=>$id,'description'=>$desc,'debit'=>$debit,'credit'=>$credit,'balance'=>$bal+$credit-$debit,'status'=>'posted']);}}
