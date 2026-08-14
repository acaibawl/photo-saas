<?php

namespace App\Http\Requests\Guardian;

use App\Http\Requests\ApiFormRequest;

class LoginGuardianRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ];
    }
}
