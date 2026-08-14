<?php

namespace App\Http\Requests\Public;

use App\Domain\Shared\EmailAddress;
use App\Http\Requests\ApiFormRequest;

class AcceptChildInvitationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (! is_string($email) || trim($email) === '') {
            return;
        }

        $this->merge([
            'email' => EmailAddress::fromString($email)->normalized(),
        ]);
    }
}
