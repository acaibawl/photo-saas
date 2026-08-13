<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class UpdateChildRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'filled', 'string', 'max:100', 'required_without_all:class_name'],
            'class_name' => ['sometimes', 'filled', 'string', 'max:50', 'required_without_all:name'],
        ];
    }
}
