<?php

namespace App\Http\Requests\MasterData;

use App\Services\Inventory\ItemPictureService;
use Illuminate\Foundation\Http\FormRequest;

class ItemPictureUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pictures' => ['required', 'array', 'min:1', 'max:'.ItemPictureService::MAX_PICTURES_PER_ITEM],
            'pictures.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'default_new_picture_index' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
