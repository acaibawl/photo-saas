<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\ApiFormRequest;

class AcceptStaffInvitationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ];
    }
}
