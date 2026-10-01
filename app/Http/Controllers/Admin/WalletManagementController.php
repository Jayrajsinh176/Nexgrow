<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.wallet');
    }

    public function credit(Request $request): RedirectResponse
    {
        return $this->applyChange($request, 'credit');
    }

    public function debit(Request $request): RedirectResponse
    {
        return $this->applyChange($request, 'debit');
    }

    protected function applyChange(Request $request, string $type): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'exists:users,user_id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $target = User::where('user_id', $validated['user_id'])->firstOrFail();

        try {
            $type === 'credit'
                ? $target->creditTokens($validated['amount'], 'admin_credit', $validated['note'] ?? null, $request->user())
                : $target->debitTokens($validated['amount'], 'admin_debit', $validated['note'] ?? null, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return back()->with('status', ucfirst($type)." of {$validated['amount']} tokens applied to {$target->name} ({$target->user_id}).");
    }
}
