<?php

namespace App\Http\Requests\Guardian;

use App\Http\Requests\ApiFormRequest;

class RefreshGuardianRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
