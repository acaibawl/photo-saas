<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class CreateChildRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'child_class_id' => ['required', 'string', 'ulid'],
            'status' => ['sometimes', 'string', 'in:enrolled,graduated,withdrawn'],
        ];
    }
}
