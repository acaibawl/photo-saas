<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class RevokeChildInvitationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
