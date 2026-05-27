<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class PostShipmentRequest extends FormRequest{public function authorize(): bool{return (bool)$this->user()?->can('shipment.post');}public function rules(): array{return [];}}
