<?php

namespace App\Support;

use App\Models\User;

/**
 * Blockchain settings for the app and the browser: the current mode, the
 * network, and the contract addresses from the deployment file.
 */
class Chain
{
    /**
     * NFT packages and daily bonus as fixed in the NFTIncome contract
     * (package price => daily bonus, both in NXD). 4X cap.
     */
    public const PACKAGES = [
        50 => 0.30,
        100 => 0.40,
        250 => 0.50,
        500 => 0.60,
        1000 => 0.70,
        2500 => 0.80,
        5000 => 1.00,
    ];

    public const CAP_MULTIPLIER = 4;

    /**
     * Income pages in chain mode (resources/views/income/chain.blade.php),
     * keyed by route name. 'contract' is the key in the deployment file and
     * in resources/js/nexgrow/abis.json; 'columns' are the credit history
     * headings, filled in the same order by resources/js/nexgrow/income.js.
     */
    public const INCOME = [
        'referral-income' => [
            'contract' => 'referralIncome',
            'title' => 'Referral Income',
            'description' => 'A share of every daily bonus paid to your downline: 5% from level 1 rising to 10% from level 6, with no level limit.',
            'columns' => ['Date', 'Level', 'From', 'Their bonus', 'Your income'],
        ],
        'team-building-benefits' => [
            'contract' => 'teamBuildingIncome',
            'title' => 'Team Building Benefits',
            'description' => '10% of level 1, 5% of levels 2–15 and 4% of levels 16–20 of your team\'s daily bonus. Needs an active NFT and at least 5 direct referrals; each direct unlocks one level.',
            'columns' => ['Date', 'Level', 'From', 'Their bonus', 'Your income'],
        ],
        'booster' => [
            'contract' => 'bonusBooster',
            'title' => '15-Day Booster',
            'description' => 'Every 15 days your team business earns 5% (from 500) up to 25% (from 100,000) of that business.',
            'columns' => ['Date', 'Period', 'Team business', 'Rate', 'Bonus'],
        ],
        'team-performance-bonus' => [
            'contract' => 'teamPerformanceBonus',
            'title' => 'Team Performance Bonus',
            'description' => 'A one-time bonus for each team target your 4 largest legs reach: 40% from the largest leg and 20% from each of the next three.',
            'columns' => ['Date', 'Target', 'Bonus'],
        ],
        'monthly-loyalty-bonus' => [
            'contract' => 'monthlyLoyaltyBonus',
            'title' => 'Monthly Loyalty Bonus',
            'description' => 'When your direct referrals buy 3,000 or more in a month, you share 5% of the whole project\'s business that month equally with every other qualifier.',
            'columns' => ['Date', 'Month', 'Your share'],
        ],
        'royalty-bonus' => [
            'contract' => 'royaltyBonus',
            'title' => 'Royalty Bonus',
            'description' => 'The first month your direct referrals buy 5,000 or more qualifies you for 15 months of an equal share of 5% of the global turnover.',
            'columns' => ['Date', 'Month', 'Your share'],
        ],
        'team-development-bonus' => [
            'contract' => 'teamDevelopmentBonus',
            'title' => 'Team Development Bonus',
            'description' => 'With 3 direct legs of 5,000 business each you qualify once for 12 months of an extra percentage on your team bonuses.',
            'columns' => ['Date', 'On bonus', 'Bonus amount', 'Your extra'],
        ],
    ];

    protected static ?array $deployment = null;

    public static function enabled(): bool
    {
        return config('blockchain.mode') === 'chain';
    }

    public static function deploymentPath(): string
    {
        return config('blockchain.deployment')
            ?: rtrim(config('blockchain.keeper.path'), '/\\')
                .'/deployments/'.config('blockchain.chain.id').'.json';
    }

    /**
     * Contract addresses keyed by name (nft, manager, rewardPool, ...), or an
     * empty array when no deployment file exists.
     *
     * @return array<string, string>
     */
    public static function contracts(): array
    {
        if (static::$deployment === null) {
            $path = static::deploymentPath();
            $json = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

            static::$deployment = is_array($json) ? $json : [];
        }

        return static::$deployment['contracts'] ?? [];
    }

    public static function isDeployed(): bool
    {
        return isset(static::contracts()['nft']);
    }

    /**
     * Everything the browser needs, embedded in the dashboard layout.
     */
    public static function browserConfig(?User $user): array
    {
        $chain = config('blockchain.chain');

        return [
            'mode' => config('blockchain.mode'),
            'chainId' => $chain['id'],
            'chainName' => $chain['name'],
            'rpcUrl' => $chain['rpc_url'],
            'explorerUrl' => $chain['explorer_url'],
            'currency' => $chain['currency'],
            'contracts' => static::contracts(),
            'startBlock' => (int) (static::$deployment['startBlock'] ?? 0),
            'user' => $user ? [
                'userId' => $user->user_id,
                'wallet' => $user->wallet_address,
                'sponsorWallet' => $user->sponsor?->wallet_address,
                'sponsorName' => $user->sponsor?->name,
            ] : null,
            'routes' => [
                'linkMessage' => route('wallet.link.message'),
                'link' => route('wallet.link'),
            ],
        ];
    }

    /**
     * The chain-mode page for one income type (see INCOME).
     */
    public static function incomeView(string $page, User $user): \Illuminate\View\View
    {
        return view('income.chain', [
            'user' => $user,
            'income' => static::INCOME[$page],
            'deployed' => static::isDeployed(),
        ]);
    }

    /**
     * Forgets the cached deployment (used by tests).
     */
    public static function flush(): void
    {
        static::$deployment = null;
    }
}
