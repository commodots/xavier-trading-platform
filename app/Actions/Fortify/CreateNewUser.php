<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'dob' => ['nullable', 'date', 'before:-18 years'],
            'bvn' => ['nullable', 'string'],
            'nin' => ['nullable', 'string'],
            'profile_image' => ['nullable', 'string'],
        ])->validate();

        $nameParts = explode(' ', $input['name'], 2);
        $firstName = trim($nameParts[0]);
        $lastName = trim($nameParts[1] ?? '');

        return User::create([
            'name' => $input['name'],
            'first_name' => $firstName,
            'last_name' => $lastName ?: null,
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'dob' => $input['dob'] ?? null,
            'bvn' => $input['bvn'] ?? null,
            'nin' => $input['nin'] ?? null,
            'profile_image' => $input['profile_image'] ?? null,
        ]);
    }
}
