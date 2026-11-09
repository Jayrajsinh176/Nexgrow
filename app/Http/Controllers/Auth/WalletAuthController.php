<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\WalletSignature;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WalletAuthController extends Controller
{
    protected const SESSION_KEY = 'wallet_auth_challenge';

    /**
     * Issue a one-time challenge nonce message for personal_sign (EIP-191).
     */
    public function message(Request $request): JsonResponse
    {
        $nonce = Str::random(32);
        $message = "Sign in to Nexgrow.\n\n"
            ."This request proves you own this wallet.\n"
            ."It does not trigger any blockchain transaction and costs no gas.\n\n"
            ."Nonce: {$nonce}\n"
            ."Timestamp: ".now()->toIso8601String();

        $request->session()->put(self::SESSION_KEY, $message);

        return response()->json(['message' => $message]);
    }

    /**
     * Authenticate or register a member using their MetaMask signature.
     */
    public function memberAuth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string'],
            'signature' => ['required', 'string'],
            'referral_code' => ['nullable', 'string'],
        ]);

        $message = $request->session()->pull(self::SESSION_KEY);

        if (! $message || ! WalletSignature::isAddress($validated['address'])) {
            return response()->json([
                'message' => 'The sign-in request expired or is invalid. Please try connecting again.',
            ], 422);
        }

        $signer = WalletSignature::recover($message, $validated['signature']);

        if (! $signer || strtolower($signer) !== strtolower($validated['address'])) {
            return response()->json([
                'message' => 'The signature does not match your connected wallet.',
            ], 422);
        }

        $address = strtolower($signer);

        // Check if user already exists
        $user = User::where('wallet_address', $address)->first();

        if ($user) {
            // Existing member -> Log in immediately
            Auth::login($user, true);
            $request->session()->regenerate();

            return response()->json([
                'status' => 'logged_in',
                'redirect' => $user->isAdmin() ? route('admin.dashboard') : route('dashboard'),
                'user_id' => $user->user_id,
            ]);
        }

        // New member -> Auto-register and log in
        $sponsor = null;
        $refCode = $validated['referral_code'] ?? $request->session()->get('ref_code');

        if ($refCode) {
            $sponsor = User::where('user_id', strtoupper(trim($refCode)))->first();
        }

        // Fallback to root admin user if no sponsor was specified
        if (! $sponsor) {
            $sponsor = User::where('is_admin', true)->first() ?? User::first();
        }

        $user = User::create([
            'name' => null,
            'wallet_address' => $address,
            'wallet_linked_at' => now(),
            'sponsor_id' => $sponsor?->id,
        ]);

        event(new Registered($user));

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'status' => 'registered',
            'redirect' => route('dashboard'),
            'user_id' => $user->user_id,
        ]);
    }

    /**
     * Authenticate an administrator using their MetaMask signature.
     */
    public function adminAuth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        $message = $request->session()->pull(self::SESSION_KEY);

        if (! $message || ! WalletSignature::isAddress($validated['address'])) {
            return response()->json([
                'message' => 'The administrator sign-in request expired. Please try again.',
            ], 422);
        }

        $signer = WalletSignature::recover($message, $validated['signature']);

        if (! $signer || strtolower($signer) !== strtolower($validated['address'])) {
            return response()->json([
                'message' => 'The signature does not match your admin wallet.',
            ], 422);
        }

        $address = strtolower($signer);
        $configuredWallets = config('admin.wallets', []);
        $isConfiguredAdmin = in_array($address, $configuredWallets, true);

        $user = User::where('wallet_address', $address)->first();

        if (! $isConfiguredAdmin && (! $user || ! $user->is_admin)) {
            return response()->json([
                'message' => "Access denied. Wallet {$address} is not an authorized administrator.",
            ], 403);
        }

        if (! $user) {
            // First time admin login for a wallet listed in config/admin.php
            $user = User::create([
                'name' => 'Nexgrow Admin',
                'wallet_address' => $address,
                'wallet_linked_at' => now(),
                'is_admin' => true,
            ]);
        } elseif (! $user->is_admin) {
            $user->forceFill(['is_admin' => true])->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'status' => 'admin_logged_in',
            'redirect' => route('admin.dashboard'),
        ]);
    }
}

