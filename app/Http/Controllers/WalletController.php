<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    protected const PER_PAGE = 10;

    /**
     * Credits that aren't earnings: admin top-ups and Profit-Sharing Slot
     * withdrawals (mostly returned principal), matching the dashboard.
     */
    protected const NON_EARNING_CREDITS = ['admin_credit', 'profit_sharing_slot_withdrawal'];

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $totals = $user->walletTransactions()
            ->reorder()
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $thisMonthEarned = $user->walletTransactions()
            ->reorder()
            ->where('type', 'credit')
            ->whereNotIn('reason', self::NON_EARNING_CREDITS)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        // Filter is either a type ("credit"/"debit") or one of the user's reasons.
        $reasons = $user->walletTransactions()->reorder()->distinct()->orderBy('reason')->pluck('reason');
        $filter = is_string($request->query('filter')) ? $request->query('filter') : '';

        $transactions = $user->walletTransactions()
            ->when(in_array($filter, ['credit', 'debit'], true), fn ($query) => $query->where('type', $filter))
            ->when($reasons->contains($filter), fn ($query) => $query->where('reason', $filter))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->onEachSide(1);

        return view('wallet', [
            'user' => $user,
            'transactions' => $transactions,
            'reasons' => $reasons,
            'filter' => $filter,
            'totalCredits' => (float) ($totals['credit'] ?? 0),
            'totalDebits' => (float) ($totals['debit'] ?? 0),
            'thisMonthEarned' => (float) $thisMonthEarned,
            'lastTransaction' => $user->walletTransactions()->orderByDesc('id')->first(),
        ]);
    }
}
