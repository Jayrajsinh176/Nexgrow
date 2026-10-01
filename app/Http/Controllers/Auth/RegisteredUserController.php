<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $sponsor = null;

        if ($request->filled('ref')) {
            $sponsor = User::where('user_id', $request->query('ref'))->first();
        }

        return view('auth.register', [
            'referralCode' => $sponsor?->user_id ?? $request->query('ref'),
            'sponsorName' => $sponsor?->name,
        ]);
    }

    public function checkReferral(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->query('code')));
        $sponsor = $code !== '' ? User::where('user_id', $code)->first() : null;

        return response()->json([
            'valid' => (bool) $sponsor,
            'name' => $sponsor?->name,
        ]);
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $field = $request->query('field');
        $value = trim((string) $request->query('value'));

        if (! in_array($field, ['email', 'mobile'], true)) {
            return response()->json(['available' => null]);
        }

        if ($value === '') {
            return response()->json(['available' => null]);
        }

        if ($field === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['available' => false, 'message' => 'Enter a valid email address']);
        }

        if ($field === 'mobile' && ! preg_match('/^[6-9]\d{9}$/', $value)) {
            return response()->json(['available' => false, 'message' => 'Enter a valid 10-digit mobile number']);
        }

        $taken = User::where($field, $value)->exists();

        return response()->json([
            'available' => ! $taken,
            'message' => $taken ? ucfirst($field).' is already registered' : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', 'unique:users,mobile'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'referral_code' => ['required', 'string', 'exists:users,user_id'],
        ], [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
        ]);

        $sponsor = User::where('user_id', $validated['referral_code'])->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'],
            'password' => Hash::make($validated['password']),
            'sponsor_id' => $sponsor->id,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
