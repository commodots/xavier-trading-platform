<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    public function prepareForValidation(): void
    {
        if (! $this->has('name') && ($this->filled('first_name') || $this->filled('last_name'))) {
            $this->merge([
                'name' => trim(implode(' ', array_filter([
                    $this->input('first_name'),
                    $this->input('last_name'),
                ]))),
            ]);
        }
    }
}
