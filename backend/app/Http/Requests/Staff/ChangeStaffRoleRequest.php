<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class ChangeStaffRoleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:owner,staff'],
        ];
    }
}
