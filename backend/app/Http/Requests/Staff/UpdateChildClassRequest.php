<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class UpdateChildClassRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
        ];
    }
}
