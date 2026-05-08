<?php
namespace App\Http\Requests\Inventory;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ReceivingEntryRequest extends FormRequest {
 protected function prepareForValidation(): void { $allowed=$this->user()?->allowedWarehouseIds()??[]; $lines=collect($this->input('lines',[]))->map(fn($l)=>is_array($l)?$l:[])->all(); $wid=$this->input('warehouse_id'); if(count($allowed)===1 && !$wid){$wid=$allowed[0];} $this->merge(['transaction_date'=>$this->normalizeDateValue($this->input('transaction_date')),'lines'=>$lines,'warehouse_id'=>$wid]); }
 public function authorize(): bool { return true; }
 public function rules(): array { $single=count($this->user()?->allowedWarehouseIds()??[])===1; return ['warehouse_id'=>[$single?'nullable':'required','integer','exists:warehouses,id'],'transaction_date'=>['required','date'],'transaction_code'=>['required',Rule::in(['PEMBELIAN','RETUR','ADJUSTMENT'])],'reference'=>['nullable','string','max:100'],'vendor_name'=>['nullable','string','max:150'],'vendor_id'=>['nullable','integer','exists:vendors,id'],'source_type'=>['nullable','string','max:50'],'source_id'=>['nullable','integer'],'notes'=>['nullable','string'],'lines'=>['required','array','min:1'],'lines.*.item_id'=>['required','integer','exists:items,id'],'lines.*.qty'=>['required','numeric','gt:0'],'lines.*.uom_id'=>['required','integer','exists:uoms,id'],'lines.*.price'=>['required','numeric','min:0']]; }
 public function withValidator($validator): void { $validator->after(function($validator): void { $u=$this->user(); if(!$u||$u->hasRole(['super-admin','Admin','Super Admin'])) return; if(!in_array((int)$this->input('warehouse_id'),$u->allowedWarehouseIds(),true)) $validator->errors()->add('warehouse_id','Warehouse tidak diizinkan untuk user login.');}); }
 private function normalizeDateValue(mixed $value): mixed { if(!is_string($value)||trim($value)==='') return $value; foreach(['Y-m-d','d/m/Y','d-m-Y','m/d/Y','m-d-Y'] as $f){try{return Carbon::createFromFormat($f,trim($value))->format('Y-m-d');}catch(\Throwable){}} return $value; }
}
