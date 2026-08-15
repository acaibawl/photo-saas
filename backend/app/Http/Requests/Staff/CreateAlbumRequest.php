<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class CreateAlbumRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'event_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
