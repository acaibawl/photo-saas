<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\ApiFormRequest;

class ListGuardianLinksRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'include_unlinked' => ['nullable', 'string', 'in:true,false,1,0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function getIncludeUnlinked(): bool
    {
        return filter_var($this->input('include_unlinked', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function getPage(): int
    {
        return max(1, (int) $this->input('page', 1));
    }

    public function getPerPage(): int
    {
        return max(1, min(100, (int) $this->input('per_page', 20)));
    }
}
