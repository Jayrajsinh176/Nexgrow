<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Chain;
use Illuminate\View\View;

/**
 * Owner actions on the Nexgrow contracts: emergency pause, payment rounds,
 * Profit-Sharing Slot profit and Team Development settings. Every action is
 * a transaction signed in the browser by the contract owner's wallet
 * (resources/js/nexgrow/admin.js); the server only renders the page.
 */
class OwnerActionsController extends Controller
{
    /**
     * Contracts with an emergency pause, and what pausing stops.
     */
    public const PAUSABLE = [
        'nft' => ['NFT Sales', 'Stops NFT purchases.'],
        'nftIncome' => ['NFT Daily Bonus', 'Stops payment rounds and claims.'],
        'referralIncome' => ['Referral Income', 'Stops payment rounds and claims. Credits keep counting.'],
        'teamBuildingIncome' => ['Team Building Benefits', 'Stops payment rounds and claims. Credits keep counting.'],
        'bonusBooster' => ['15-Day Booster', 'Stops payment rounds and claims. Credits keep counting.'],
        'teamPerformanceBonus' => ['Team Performance Bonus', 'Stops payment rounds and claims. Credits keep counting.'],
        'monthlyLoyaltyBonus' => ['Monthly Loyalty Bonus', 'Stops payment rounds and claims. Credits keep counting.'],
        'royaltyBonus' => ['Royalty Bonus', 'Stops payment rounds and claims. Credits keep counting.'],
        'teamDevelopmentBonus' => ['Team Development Bonus', 'Stops payment rounds and claims. Credits keep counting.'],
        'rewardPool' => ['Reward Pool', 'Stops every income payout. Deposits stay open.'],
        'liquidityPool' => ['Liquidity Pool', 'Stops payouts. Deposits stay open.'],
        'profitSharingSlot' => ['Profit-Sharing Slot', 'Stops slot purchases and profit deposits. Withdrawals stay open.'],
    ];

    /**
     * Team bonuses the Team Development Bonus can add its extra to.
     */
    public const DEVELOPMENT_SOURCES = [
        'referralIncome', 'teamBuildingIncome', 'bonusBooster',
        'teamPerformanceBonus', 'monthlyLoyaltyBonus', 'royaltyBonus',
    ];

    public function __invoke(): View
    {
        $income = collect(Chain::INCOME)->mapWithKeys(fn ($i) => [$i['contract'] => $i['title']]);

        return view('admin.owner.index', [
            'deployed' => Chain::isDeployed(),
            'pausable' => self::PAUSABLE,
            'payoutModules' => ['nftIncome' => 'NFT Daily Bonus'] + $income->all(),
            'developmentSources' => $income->only(self::DEVELOPMENT_SOURCES)->all(),
        ]);
    }
}
