<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreShipmentRequest extends FormRequest{
 public function authorize(): bool{return (bool)$this->user()?->can('shipment.create');}
 public function rules(): array{return [
'shipment_date'=>['required','date'],'warehouse_id'=>['nullable','integer'],'driver_name'=>['nullable','string','max:150'],'vehicle_no'=>['nullable','string','max:100'],'courier_name'=>['nullable','string','max:150'],'tracking_number'=>['nullable','string','max:150'],'notes'=>['nullable','string'],
'lines'=>['required','array','min:1'],'lines.*.sale_line_id'=>['required','integer'],'lines.*.item_id'=>['required','integer'],'lines.*.batch_id'=>['nullable','integer'],'lines.*.facility_scheme_id'=>['nullable','integer'],'lines.*.uom_id'=>['nullable','integer'],'lines.*.qty_shipped'=>['required','numeric','min:0.0001'],'lines.*.notes'=>['nullable','string']
];}
}
