<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class PrintChildInvitationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
        ];
    }
}
