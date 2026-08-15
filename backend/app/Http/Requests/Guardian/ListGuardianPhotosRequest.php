<?php

namespace App\Http\Requests\Guardian;

use App\Http\Requests\ApiFormRequest;

class ListGuardianPhotosRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'child_id' => ['nullable', 'ulid'],
            'album_id' => ['nullable', 'ulid'],
            'event_date_from' => ['nullable', 'date_format:Y-m-d'],
            'event_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:event_date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
