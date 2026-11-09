<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $sponsor = null;

        if ($request->filled('ref')) {
            $sponsor = User::where('user_id', $request->query('ref'))->first();
            $request->session()->put('ref_code', $sponsor?->user_id ?? $request->query('ref'));
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
            'name' => $sponsor?->name ?: ($sponsor ? 'Member ' . $sponsor->user_id : null),
        ]);
    }
}
