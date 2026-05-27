<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class CancelShipmentRequest extends FormRequest{public function authorize(): bool{return (bool)$this->user()?->can('shipment.cancel');}public function rules(): array{return ['cancel_reason'=>['required','string','max:500']];}}
