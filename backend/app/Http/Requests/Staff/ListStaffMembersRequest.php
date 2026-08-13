<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class ListStaffMembersRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'role' => ['nullable', 'string', 'in:owner,staff'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
