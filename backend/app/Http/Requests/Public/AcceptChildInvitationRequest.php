<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\ApiFormRequest;

class AcceptChildInvitationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:guardians,email'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ];
    }
}
