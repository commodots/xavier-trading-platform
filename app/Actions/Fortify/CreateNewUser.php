<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        $mobilePhone = $input['mobile_phone_no'] ?? null;
        $input['phone'] = filled($mobilePhone) ? $mobilePhone : ($input['phone'] ?? null);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255', 'regex:/^\s*\S+\s+\S+.*$/'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'dob' => ['required', 'date', 'before:-18 years'],
            'phone' => ['required', 'string', 'max:30', Rule::unique(User::class, 'phone')],
            'mobile_phone_no' => ['nullable', 'string', 'max:30'],
            'gender_cd' => ['required', 'string', 'max:20'],
            'address_street' => ['required', 'string', 'max:255'],
            'address_city' => ['required', 'string', 'max:100'],
            'address_country_cd' => ['required', 'string', 'max:20'],
            'bank_cd' => ['required', 'string', 'max:50'],
            'bank_account_name' => ['required', 'string', 'max:255'],
            'bank_account_no' => ['required', 'string', 'max:50'],
            'bank_branch_name' => ['required', 'string', 'max:255'],
            'bank_address_details' => ['required', 'string', 'max:500'],
            'bank_bvn_no' => ['required', 'digits:11'],
            'bvn' => ['nullable', 'string'],
            'nin' => ['nullable', 'string'],
            'profile_image' => ['nullable', 'string'],
        ])->validate();

        $nameParts = preg_split('/\s+/', trim($input['name']), -1, PREG_SPLIT_NO_EMPTY);
        $firstName = array_shift($nameParts);
        $lastName = implode(' ', $nameParts);

        return DB::transaction(function () use ($input, $firstName, $lastName): User {
            $user = User::create([
                'name' => $input['name'],
                'first_name' => $firstName,
                'last_name' => $lastName ?: null,
                'email' => $input['email'],
                'phone' => $input['phone'],
                'gender' => $input['gender_cd'],
                'address' => $input['address_street'].', '.$input['address_city'],
                'country' => $input['address_country_cd'],
                'password' => Hash::make($input['password']),
                'dob' => $input['dob'],
                'bvn' => $input['bvn'] ?? $input['bank_bvn_no'],
                'nin' => $input['nin'] ?? null,
                'profile_image' => $input['profile_image'] ?? null,
            ]);

            $user->cslCustomerProfile()->create([
                'mobile_phone_no' => $input['phone'],
                'gender_cd' => $input['gender_cd'],
                'address_street' => $input['address_street'],
                'address_city' => $input['address_city'],
                'address_country_cd' => $input['address_country_cd'],
                'bank_cd' => $input['bank_cd'],
                'bank_account_name' => $input['bank_account_name'],
                'bank_account_no' => $input['bank_account_no'],
                'bank_branch_name' => $input['bank_branch_name'],
                'bank_address_details' => $input['bank_address_details'],
                'bank_bvn_no' => $input['bank_bvn_no'],
            ]);

            return $user;
        });
    }
}
