<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class UomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uomId = $this->route('uom')?->id;

        return [
            'code' => ['required', 'string', 'max:20', 'unique:uoms,code,'. $uomId],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
