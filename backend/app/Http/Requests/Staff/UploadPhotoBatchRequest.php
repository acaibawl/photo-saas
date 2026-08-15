<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class UploadPhotoBatchRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'album_id' => ['nullable', 'ulid'],
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,heic', 'max:12288'],
            'price' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'child_ids' => ['nullable', 'array', 'max:50'],
            'child_ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }
}
