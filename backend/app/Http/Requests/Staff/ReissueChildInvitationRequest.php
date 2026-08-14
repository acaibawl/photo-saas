<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class ReissueChildInvitationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
