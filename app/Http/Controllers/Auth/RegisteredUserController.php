<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
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
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'bvn' => 'nullable|string',
            'nin' => 'nullable|string',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $nameParts = explode(' ', $request->name, 2);
        $firstName = trim($nameParts[0]);
        $lastName = trim($nameParts[1] ?? '');

        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            $profileImagePath = $request->file('profile_image')->store('profile_images', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'first_name' => $firstName,
            'last_name' => $lastName ?: null,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'bvn' => $request->bvn,
            'nin' => $request->nin,
            'profile_image' => $profileImagePath,
        ]);

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
