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
            'name' => ['filled', 'string', 'max:100', 'required_without_all:child_class_id'],
            'child_class_id' => ['filled', 'string', 'ulid', 'required_without_all:name'],
        ];
    }
}
