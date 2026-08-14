<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class UnlinkGuardianLinkRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
            'confirm_text' => ['required', 'string', 'in:UNLINK'],
        ];
    }
}
