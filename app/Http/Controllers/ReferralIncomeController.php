<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralIncomeController extends Controller
{
    protected const PER_PAGE = 10;

    public const DATE_RANGES = [
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
    ];

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $range = $request->query('range');
        $range = is_string($range) && isset(self::DATE_RANGES[$range]) ? $range : null;

        $incomes = $user->referralIncomesReceived()
            ->with('sourceUser')
            ->when($search !== '', fn ($query) => $query->whereHas('sourceUser', fn ($source) => $source
                ->where('name', 'like', "%{$search}%")
                ->orWhere('user_id', 'like', "%{$search}%")))
            ->when($range, fn ($query) => $query->whereBetween('created_at', $this->rangeBounds($range)))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->onEachSide(1);

        return view('referral-income', [
            'incomes' => $incomes,
            'search' => $search,
            'range' => $range,
            'total' => $user->referralIncomesReceived()->sum('amount'),
            'thisMonth' => $user->referralIncomesReceived()->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'latest' => $user->referralIncomesReceived()->orderByDesc('id')->first(),
        ]);
    }

    protected function rangeBounds(string $range): array
    {
        return match ($range) {
            'today' => [now()->startOfDay(), now()],
            '7d' => [now()->subDays(7), now()],
            '30d' => [now()->subDays(30), now()],
            'this_month' => [now()->startOfMonth(), now()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
        };
    }
}
