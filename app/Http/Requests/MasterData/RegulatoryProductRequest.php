<?php
namespace App\Http\Requests\MasterData;
use App\Models\Regulatory\RegulatoryProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class RegulatoryProductRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'product_type'=>['required',Rule::in([RegulatoryProduct::TYPE_DRUG,RegulatoryProduct::TYPE_MEDICAL_DEVICE])],
        'source_id'=>['required','exists:regulatory_sources,id'],'nie'=>['required','string','max:100'],'source_code'=>['nullable','string','max:255'],'product_name_source'=>['nullable','string','max:255'],'industry_name'=>['nullable','string','max:255'],'raw_payload'=>['nullable','array'],'dosage_form'=>['nullable','string','max:255'],'strength'=>['nullable','string','max:255'],'commodity_type'=>['nullable','string','max:255'],'raw_packaging_text'=>['nullable','string'],'raw_composition_text'=>['nullable','string'],
        'brand'=>['nullable','string','max:255'],'license_type'=>['nullable',Rule::in(['AKD','AKL'])],'registration_date'=>['nullable','date'],'expiry_date'=>['nullable','date'],'sub_category'=>['nullable','string','max:255'],'device_type'=>['nullable','string','max:255'],'product_group'=>['nullable','string','max:255'],'model_type'=>['nullable','string'],'device_class'=>['nullable','string','max:255'],'risk_class'=>['nullable','string','max:255'],'registrant_name'=>['nullable','string','max:255'],'registrant_address'=>['nullable','string'],'manufacturer_name'=>['nullable','string','max:255'],'manufacturer_address'=>['nullable','string'],'manufacturer_name_2'=>['nullable','string','max:255'],
    ]; }
    public function withValidator($validator): void {
        $validator->after(function($v){
            $type=$this->input('product_type');
            if($type===RegulatoryProduct::TYPE_DRUG && !trim((string)$this->input('product_name_source'))){$v->errors()->add('product_name_source','Nama produk wajib untuk Obat.');}
            if($type===RegulatoryProduct::TYPE_MEDICAL_DEVICE && !trim((string)$this->input('product_name_source')) && !trim((string)$this->input('brand'))){$v->errors()->add('brand','Brand atau nama produk wajib untuk Alat Kesehatan.');}
        });
    }
}
