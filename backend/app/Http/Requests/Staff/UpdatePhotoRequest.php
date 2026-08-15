<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class UpdatePhotoRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'album_id' => ['sometimes', 'nullable', 'ulid'],
            'price' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'child_ids' => ['sometimes', 'array', 'max:50'],
            'child_ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }
}
