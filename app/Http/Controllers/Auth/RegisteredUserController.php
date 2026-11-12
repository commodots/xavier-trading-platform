<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->merge([
            'phone' => $request->input('mobile_phone_no') ?: $request->input('phone'),
        ]);

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^\s*\S+\s+\S+.*$/'],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'dob' => 'required|date|before:-18 years',
            'phone' => 'required|string|max:30|unique:users,phone',
            'mobile_phone_no' => 'nullable|string|max:30',
            'gender_cd' => 'required|string|max:20',
            'address_street' => 'required|string|max:255',
            'address_city' => 'required|string|max:100',
            'address_country_cd' => 'required|string|max:20',
            'bank_cd' => 'required|string|max:50',
            'bank_account_name' => 'required|string|max:255',
            'bank_account_no' => 'required|string|max:50',
            'bank_branch_name' => 'required|string|max:255',
            'bank_address_details' => 'required|string|max:500',
            'bank_bvn_no' => 'required|digits:11',
            'bvn' => 'nullable|string',
            'nin' => 'nullable|string',
            'profile_image' => 'nullable|string',
        ]);

        $nameParts = preg_split('/\s+/', trim($request->name), -1, PREG_SPLIT_NO_EMPTY);
        $firstName = array_shift($nameParts);
        $lastName = implode(' ', $nameParts);

        $user = DB::transaction(function () use ($request, $firstName, $lastName): User {
            $user = User::create([
                'name' => $request->name,
                'first_name' => $firstName,
                'last_name' => $lastName ?: null,
                'email' => $request->email,
                'phone' => $request->phone,
                'gender' => $request->gender_cd,
                'address' => $request->address_street.', '.$request->address_city,
                'country' => $request->address_country_cd,
                'password' => Hash::make($request->password),
                'dob' => $request->dob,
                'bvn' => $request->bvn ?? $request->bank_bvn_no,
                'nin' => $request->nin,
                'profile_image' => $request->profile_image,
            ]);

            $user->cslCustomerProfile()->create([
                'mobile_phone_no' => $request->phone,
                'gender_cd' => $request->gender_cd,
                'address_street' => $request->address_street,
                'address_city' => $request->address_city,
                'address_country_cd' => $request->address_country_cd,
                'bank_cd' => $request->bank_cd,
                'bank_account_name' => $request->bank_account_name,
                'bank_account_no' => $request->bank_account_no,
                'bank_branch_name' => $request->bank_branch_name,
                'bank_address_details' => $request->bank_address_details,
                'bank_bvn_no' => $request->bank_bvn_no,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::guard()->login($user);
        $request->setUserResolver(fn () => $user);

        if ($request->expectsJson()) {
            $token = $user->createToken('xavier')->plainTextToken;

            return response()->json([
                'message' => 'User registered successfully',
                'token' => $token,
                'user' => $user,
            ]);
        }

        return redirect(route('verification.notice', absolute: false));
    }
}
