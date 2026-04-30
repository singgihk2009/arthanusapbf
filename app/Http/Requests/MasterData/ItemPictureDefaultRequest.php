<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class ItemPictureDefaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'picture_id' => ['required', 'integer', 'exists:item_pictures,id'],
        ];
    }
}
