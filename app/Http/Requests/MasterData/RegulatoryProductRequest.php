<?php
namespace App\Http\Requests\MasterData;
use Illuminate\Foundation\Http\FormRequest;
class RegulatoryProductRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return ['source_id'=>['required','exists:regulatory_sources,id'],'nie'=>['required','string','max:100'],'source_code'=>['nullable','string','max:255'],'product_name_source'=>['required','string','max:255'],'industry_name'=>['nullable','string','max:255'],'dosage_form'=>['nullable','string','max:255'],'strength'=>['nullable','string','max:255'],'commodity_type'=>['nullable','string','max:255'],'raw_packaging_text'=>['nullable','string'],'raw_composition_text'=>['nullable','string']]; }
}
