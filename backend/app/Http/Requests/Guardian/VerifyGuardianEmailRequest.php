<?php

namespace App\Http\Requests\Guardian;

use App\Http\Requests\ApiFormRequest;

class VerifyGuardianEmailRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:255'],
            'hash' => ['required', 'string'],
            'signature' => ['required', 'string'],
            'expires' => ['required', 'integer'],
        ];
    }
}
