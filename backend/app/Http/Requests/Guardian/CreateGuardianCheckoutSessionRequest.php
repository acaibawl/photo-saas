<?php

namespace App\Http\Requests\Guardian;

use App\Http\Requests\ApiFormRequest;

class CreateGuardianCheckoutSessionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo_ids' => ['required', 'array', 'min:1', 'max:50'],
            'photo_ids.*' => ['ulid', 'distinct'],
            'checkout_amount' => ['required', 'integer', 'min:1'],
            'success_url' => ['required', 'url', 'starts_with:https://'],
            'cancel_url' => ['required', 'url', 'starts_with:https://'],
        ];
    }
}
