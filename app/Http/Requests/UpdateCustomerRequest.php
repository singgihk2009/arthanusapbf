<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $id = is_object($customer) ? $customer->id : $customer;

        return [
            'customer_code' => ['required','string','max:50',Rule::unique('customers','customer_code')->ignore($id)],
            'customer_name' => ['required','string','max:255'],
            'customer_type' => ['nullable','string','max:100'],
            'contact_person' => ['nullable','string','max:150'],
            'phone' => ['nullable','string','max:50'],
            'email' => ['nullable','email','max:150'],
            'address' => ['nullable','string'],
            'city' => ['nullable','string','max:150'],
            'province' => ['nullable','string','max:150'],
            'postal_code' => ['nullable','string','max:20'],
            'country' => ['nullable','string','max:150'],
            'npwp' => ['nullable','string','max:50'],
            'price_list_id' => ['nullable','integer'],
            'payment_term_days' => ['nullable','integer','min:0','max:365'],
            'credit_limit' => ['nullable','numeric','min:0'],
            'salesman_id' => ['nullable','integer'],
            'status' => ['required','in:active,inactive'],
            'notes' => ['nullable','string'],
        ];
    }
}
