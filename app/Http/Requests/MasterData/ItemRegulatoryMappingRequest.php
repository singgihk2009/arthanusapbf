<?php
namespace App\Http\Requests\MasterData;
use Illuminate\Foundation\Http\FormRequest;
class ItemRegulatoryMappingRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return ['item_id'=>['required','exists:items,id'],'regulatory_product_id'=>['required','exists:regulatory_products,id'],'notes'=>['nullable','string']]; }
}
