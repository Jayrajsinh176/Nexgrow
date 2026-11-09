<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\WalletSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Links a user's on-chain wallet to their account. The user proves they own
 * the wallet by signing a one-time message; a wallet can belong to only one
 * account, and an account links one wallet. Users cannot change a linked
 * wallet from the app; a developer changes it directly in the database.
 */
class WalletLinkController extends Controller
{
    protected const SESSION_KEY = 'wallet_link_message';

    public function message(Request $request): JsonResponse
    {
        $user = $request->user();

        $message = "Link this wallet to Nexgrow account {$user->user_id}.\n\n"
            .'This does not cost anything or allow any transaction.'
            ."\n\nNonce: ".Str::random(24);

        $request->session()->put(self::SESSION_KEY, $message);

        return response()->json(['message' => $message]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($user->wallet_address !== null) {
            return response()->json(['message' => 'A wallet is already linked to this account.'], 422);
        }

        $message = $request->session()->pull(self::SESSION_KEY);

        if ($message === null || ! WalletSignature::isAddress($validated['address'])) {
            return response()->json(['message' => 'The link request expired. Please try again.'], 422);
        }

        $signer = WalletSignature::recover($message, $validated['signature']);

        if ($signer === null || strtolower($signer) !== strtolower($validated['address'])) {
            return response()->json(['message' => 'The signature does not match this wallet.'], 422);
        }

        $address = strtolower($signer);

        if (User::where('wallet_address', $address)->where('id', '!=', $user->id)->exists()) {
            return response()->json(['message' => 'This wallet is already linked to another account.'], 422);
        }

        $user->forceFill([
            'wallet_address' => $address,
            'wallet_linked_at' => now(),
        ])->save();

        return response()->json(['wallet_address' => $address]);
    }
}
