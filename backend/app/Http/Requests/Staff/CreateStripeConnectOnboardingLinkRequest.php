<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class CreateStripeConnectOnboardingLinkRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_url' => ['required', 'string', 'url', 'starts_with:https://'],
            'refresh_url' => ['required', 'string', 'url', 'starts_with:https://'],
        ];
    }
}
