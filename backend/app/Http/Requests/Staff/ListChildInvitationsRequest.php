<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class ListChildInvitationsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:active,used,expired,revoked'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
