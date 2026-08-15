<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class ListPhotosRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'album_id' => ['nullable', 'ulid'],
            'child_id' => ['nullable', 'ulid'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'price_status' => ['nullable', 'string', 'in:set,unset'],
            'price_min' => ['nullable', 'integer', 'min:1'],
            'price_max' => ['nullable', 'integer', 'min:1', 'gte:price_min'],
            'preview_status' => ['nullable', 'string', 'in:queued,ready,failed'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
