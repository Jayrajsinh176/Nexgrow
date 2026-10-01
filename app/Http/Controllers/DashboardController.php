<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Wallet credit reasons that count as earned income, in the order the
     * Income Summary lists them. Admin credits and Profit-Sharing Slot
     * withdrawals (mostly returned principal) are deliberately excluded.
     */
    protected const INCOME_SOURCES = [
        'daily_bonus' => 'NFT Daily Bonus',
        'referral_income' => 'Referral Income',
        'team_building_benefit' => 'Team Building Benefits',
        'bonus_booster' => '15-Day Booster',
        'team_performance_bonus' => 'Team Performance Bonus',
    ];

    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('sponsor');
        $team = $user->teamMembers();

        $incomeByReason = $user->walletTransactions()
            ->reorder()
            ->where('type', 'credit')
            ->whereIn('reason', array_keys(self::INCOME_SOURCES))
            ->selectRaw('reason, SUM(amount) as total')
            ->groupBy('reason')
            ->pluck('total', 'reason');

        $incomeSummary = collect(self::INCOME_SOURCES)->map(fn (string $label, string $reason) => [
            'reason' => $reason,
            'label' => $label,
            'total' => (float) ($incomeByReason[$reason] ?? 0),
        ])->values();

        $teamByLevel = $team->countBy('team_level')->sortKeys();

        $activities = $user->walletTransactions()->take(4)->get()
            ->map(fn ($transaction) => [
                'kind' => $transaction->type,
                'title' => str($transaction->reason)->replace('_', ' ')->title()->toString(),
                'detail' => ($transaction->type === 'credit' ? '+' : '-').number_format((float) $transaction->amount, 2).' NXD',
                'at' => $transaction->created_at,
            ])
            ->concat(
                $team->sortByDesc('created_at')->take(4)->map(fn ($member) => [
                    'kind' => 'member',
                    'title' => "{$member->name} joined",
                    'detail' => "Level {$member->team_level}",
                    'at' => $member->created_at,
                ])
            )
            ->sortByDesc('at')
            ->take(4)
            ->values();

        return view('dashboard', [
            'user' => $user,
            'activeNft' => $user->activeNft()?->load('nftPackage'),
            'directReferralsCount' => $teamByLevel->get(1, 0),
            'totalTeamCount' => $team->count(),
            'teamByLevel' => $teamByLevel->take(5),
            'incomeSummary' => $incomeSummary,
            'totalEarnings' => $incomeSummary->sum('total'),
            'activities' => $activities,
        ]);
    }
}
