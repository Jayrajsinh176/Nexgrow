<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NftPackage;
use App\Models\ProfitSharingSlot;
use App\Models\User;
use App\Models\UserNft;
use App\Models\WalletTransaction;
use App\Support\Chain;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $totalUsers = User::count();
        $linkedWalletsCount = User::whereNotNull('wallet_address')->count();
        $activeNftsCount = UserNft::where('status', 'active')->count();
        $totalNftVolume = (float) UserNft::sum('purchase_price');
        $totalSlotsSold = ProfitSharingSlot::count();
        $totalSlotsVolume = (float) ProfitSharingSlot::sum('amount');

        // Total earnings distributed (excluding non-earning credits like admin top-up)
        $nonEarningCredits = ['admin_credit', 'profit_sharing_slot_withdrawal'];
        $totalDistributedIncome = (float) WalletTransaction::where('type', 'credit')
            ->whereNotIn('reason', $nonEarningCredits)
            ->sum('amount');

        // Detailed income stream breakdown from ledger
        $creditsByReason = WalletTransaction::where('type', 'credit')
            ->selectRaw('reason, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('reason')
            ->get()
            ->keyBy('reason');

        $incomeStreams = [
            'daily_nft_bonus' => [
                'title' => 'Daily NFT Bonus',
                'description' => 'Daily ROI bonus on active packages up to 4X cap',
                'total' => (float) ($creditsByReason['daily_nft_bonus']->total ?? 0),
                'count' => (int) ($creditsByReason['daily_nft_bonus']->count ?? 0),
                'route' => 'admin.incomes.index',
                'param' => 'daily_nft_bonus',
            ],
            'referral_income' => [
                'title' => 'Referral Income',
                'description' => 'Downline daily bonus share (5% to 10% across levels)',
                'total' => (float) ($creditsByReason['referral_income']->total ?? 0),
                'count' => (int) ($creditsByReason['referral_income']->count ?? 0),
                'route' => 'admin.incomes.index',
                'param' => 'referral_income',
            ],
            'team_building_benefit' => [
                'title' => 'Team Building Benefits',
                'description' => '20-level team benefits on downline daily bonus',
                'total' => (float) ($creditsByReason['team_building_benefit']->total ?? 0),
                'count' => (int) ($creditsByReason['team_building_benefit']->count ?? 0),
                'route' => 'admin.incomes.index',
                'param' => 'team_building_benefit',
            ],
            'bonus_booster' => [
                'title' => '15-Day Booster',
                'description' => 'Bi-weekly bonus based on team volume tiers',
                'total' => (float) ($creditsByReason['bonus_booster']->total ?? 0),
                'count' => (int) ($creditsByReason['bonus_booster']->count ?? 0),
                'route' => 'admin.incomes.index',
                'param' => 'bonus_booster',
            ],
            'team_performance_bonus' => [
                'title' => 'Team Performance Bonus',
                'description' => 'One-time bonus on reaching leg volume targets (40/20/20/20)',
                'total' => (float) ($creditsByReason['team_performance_bonus']->total ?? 0),
                'count' => (int) ($creditsByReason['team_performance_bonus']->count ?? 0),
                'route' => 'admin.incomes.index',
                'param' => 'team_performance_bonus',
            ],
            'monthly_loyalty_bonus' => [
                'title' => 'Monthly Loyalty Bonus',
                'description' => 'Equal share of 5% monthly direct business pool',
                'total' => (float) ($creditsByReason['monthly_loyalty_bonus']->total ?? 0),
                'count' => (int) ($creditsByReason['monthly_loyalty_bonus']->count ?? 0),
                'route' => 'admin.incomes.index',
                'param' => 'monthly_loyalty_bonus',
            ],
            'royalty_bonus' => [
                'title' => 'Royalty Bonus',
                'description' => '15-month pool share for qualifying direct turnover',
                'total' => (float) ($creditsByReason['royalty_bonus']->total ?? 0),
                'count' => (int) ($creditsByReason['royalty_bonus']->count ?? 0),
                'route' => 'admin.incomes.index',
                'param' => 'royalty_bonus',
            ],
            'team_development_bonus' => [
                'title' => 'Team Development Bonus',
                'description' => 'Extra 5% on eligible team bonuses for 12 months',
                'total' => (float) ($creditsByReason['team_development_bonus']->total ?? 0),
                'count' => (int) ($creditsByReason['team_development_bonus']->count ?? 0),
                'route' => 'admin.incomes.index',
                'param' => 'team_development_bonus',
            ],
        ];

        $recentUsers = User::with('sponsor')->latest()->take(6)->get();
        $recentTransactions = WalletTransaction::with('user')->latest()->take(8)->get();

        return view('admin.dashboard', [
            'totalUsers' => $totalUsers,
            'linkedWalletsCount' => $linkedWalletsCount,
            'activeNftsCount' => $activeNftsCount,
            'totalNftVolume' => $totalNftVolume,
            'totalSlotsSold' => $totalSlotsSold,
            'totalSlotsVolume' => $totalSlotsVolume,
            'totalDistributedIncome' => $totalDistributedIncome,
            'incomeStreams' => $incomeStreams,
            'recentUsers' => $recentUsers,
            'recentTransactions' => $recentTransactions,
            'blockchainMode' => config('blockchain.mode'),
            'isDeployed' => Chain::isDeployed(),
            'contracts' => Chain::contracts(),
        ]);
    }
}

