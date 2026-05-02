<?php
namespace App\Http\Requests\Procurement;
use Illuminate\Foundation\Http\FormRequest;
class StorePurchaseOrderRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['vendor_id'=>['nullable','integer']]; } }
