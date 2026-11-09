<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNft;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    protected const PER_PAGE = 15;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $walletFilter = (string) $request->query('wallet', '');
        $nftFilter = (string) $request->query('nft', '');

        $query = User::with(['sponsor', 'nfts.nftPackage'])
            ->withCount('referrals');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('user_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('wallet_address', 'like', "%{$search}%");
            });
        }

        if ($walletFilter === 'linked') {
            $query->whereNotNull('wallet_address');
        } elseif ($walletFilter === 'unlinked') {
            $query->whereNull('wallet_address');
        }

        if ($nftFilter === 'active') {
            $query->whereHas('nfts', fn ($q) => $q->where('status', 'active'));
        } elseif ($nftFilter === 'completed') {
            $query->whereHas('nfts', fn ($q) => $q->where('status', 'completed'))
                ->whereDoesntHave('nfts', fn ($q) => $q->where('status', 'active'));
        } elseif ($nftFilter === 'none') {
            $query->whereDoesntHave('nfts');
        }

        $users = $query->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Calculate earnings for the current page users
        $userIds = $users->pluck('id');
        $earningsByUser = WalletTransaction::whereIn('user_id', $userIds)
            ->where('type', 'credit')
            ->whereNotIn('reason', ['admin_credit', 'profit_sharing_slot_withdrawal'])
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(amount) as total')
            ->pluck('total', 'user_id');

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'walletFilter' => $walletFilter,
            'nftFilter' => $nftFilter,
            'earningsByUser' => $earningsByUser,
            'totalUsersCount' => User::count(),
            'linkedCount' => User::whereNotNull('wallet_address')->count(),
            'activeNftUsersCount' => User::whereHas('nfts', fn ($q) => $q->where('status', 'active'))->count(),
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['sponsor', 'referrals', 'nfts.nftPackage', 'profitSharingSlots']);

        $teamMembers = $user->teamMembers();
        $totalTeamCount = $teamMembers->count();

        // Income breakdown by reason
        $incomeByReason = $user->walletTransactions()
            ->where('type', 'credit')
            ->groupBy('reason')
            ->selectRaw('reason, SUM(amount) as total, COUNT(*) as count')
            ->get()
            ->keyBy('reason');

        $reasons = [
            'daily_nft_bonus' => 'Daily NFT Bonus',
            'referral_income' => 'Referral Income',
            'team_building_benefit' => 'Team Building Benefit',
            'bonus_booster' => '15-Day Bonus Booster',
            'team_performance_bonus' => 'Team Performance Bonus',
            'monthly_loyalty_bonus' => 'Monthly Loyalty Bonus',
            'royalty_bonus' => 'Royalty Bonus',
            'team_development_bonus' => 'Team Development Bonus',
            'profit_sharing_slot_withdrawal' => 'Profit Sharing Slot Returns',
        ];

        $breakdown = [];
        $totalEarned = 0.0;

        foreach ($reasons as $reason => $label) {
            $amount = (float) ($incomeByReason[$reason]->total ?? 0);
            $count = (int) ($incomeByReason[$reason]->count ?? 0);

            if ($reason !== 'profit_sharing_slot_withdrawal') {
                $totalEarned += $amount;
            }

            $breakdown[$reason] = [
                'label' => $label,
                'amount' => $amount,
                'count' => $count,
            ];
        }

        // Recent transactions for this user
        $transactions = $user->walletTransactions()
            ->orderByDesc('id')
            ->take(20)
            ->get();

        return view('admin.users.show', [
            'user' => $user,
            'totalTeamCount' => $totalTeamCount,
            'totalEarned' => $totalEarned,
            'incomeBreakdown' => $breakdown,
            'transactions' => $transactions,
            'activeNft' => $user->activeNft(),
        ]);
    }
}

