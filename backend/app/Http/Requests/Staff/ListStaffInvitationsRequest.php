<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class ListStaffInvitationsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:pending,accepted,revoked,expired'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
