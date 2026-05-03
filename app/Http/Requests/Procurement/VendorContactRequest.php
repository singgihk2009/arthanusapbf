<?php
namespace App\Http\Requests\Procurement;
use Illuminate\Foundation\Http\FormRequest;

class VendorContactRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'full_name' => ['required','string','max:255'],
            'email' => ['nullable','email','max:255','required_if:can_login,true'],
            'phone' => ['nullable','string','max:100'],
            'mobile' => ['nullable','string','max:100'],
            'position_title' => ['nullable','string','max:255'],
            'department' => ['nullable','string','max:255'],
            'contact_role' => ['nullable','string','max:100'],
            'is_primary' => ['nullable','boolean'],
            'can_login' => ['nullable','boolean'],
            'status' => ['nullable','in:active,inactive'],
            'notes' => ['nullable','string'],
        ];
    }
}
