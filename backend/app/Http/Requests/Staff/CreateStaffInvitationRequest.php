<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class CreateStaffInvitationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:staff'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
