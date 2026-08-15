<?php

namespace App\Http\Requests\Guardian;

use App\Http\Requests\ApiFormRequest;

class ListGuardianAlbumsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'child_id' => ['nullable', 'ulid'],
        ];
    }
}
