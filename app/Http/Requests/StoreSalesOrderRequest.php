<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreSalesOrderRequest extends FormRequest {
public function authorize(): bool { return (bool) $this->user()?->can('sales-order.create'); }
public function rules(): array { return [
'warehouse_id'=>['required','integer'],'document_date'=>['required','date'],'expected_delivery_date'=>['nullable','date','after_or_equal:document_date'],'price_list_id'=>['nullable','integer'],'notes'=>['nullable','string'],
'lines'=>['required','array','min:1'],'lines.*.id'=>['nullable','integer'],'lines.*.item_id'=>['required','integer'],'lines.*.uom_id'=>['nullable','integer'],'lines.*.facility_scheme_id'=>['nullable','integer'],'lines.*.qty_sold'=>['required','numeric','min:0.0001'],'lines.*.unit_price'=>['required','numeric','min:0'],'lines.*.discount_percent'=>['nullable','numeric','min:0','max:100'],'lines.*.tax_percent'=>['nullable','numeric','min:0','max:100'],'lines.*.notes'=>['nullable','string'],
]; }
}
