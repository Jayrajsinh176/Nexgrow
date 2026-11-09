<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BonusBooster;
use App\Models\MonthlyLoyaltyQualification;
use App\Models\ReferralIncome;
use App\Models\RoyaltyQualification;
use App\Models\TeamBuildingBenefit;
use App\Models\TeamDevelopmentQualification;
use App\Models\TeamPerformanceBonus;
use App\Models\UserNft;
use App\Models\WalletTransaction;
use App\Support\Chain;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin income pages: an overview of every stream (/admin/incomes) and one
 * page per stream (/admin/incomes/{stream}), listed in the sidebar dropdown.
 */
class IncomeManagementController extends Controller
{
    protected const PER_PAGE = 20;

    protected const OVERVIEW_PER_PAGE = 10;

    protected const TOP_EARNERS = 5;

    /**
     * Income streams keyed by wallet transaction reason.
     */
    public const STREAMS = [
        'daily_nft_bonus' => [
            'title' => 'Daily NFT Bonus',
            'contract' => 'nftIncome',
            'description' => 'Daily bonus earned by active NFT packages (0.30 to 1.00 NXD per day, 4X cap).',
        ],
        'referral_income' => [
            'title' => 'Referral Income',
            'contract' => 'referralIncome',
            'description' => 'Direct and upline percentage (5%–10%) of daily NFT bonuses across unlimited levels.',
        ],
        'team_building_benefit' => [
            'title' => 'Team Building Benefits',
            'contract' => 'teamBuildingIncome',
            'description' => '20-generation team bonus on daily NFT rewards for qualifiers with 5+ direct referrals.',
        ],
        'bonus_booster' => [
            'title' => '15-Day Bonus Booster',
            'contract' => 'bonusBooster',
            'description' => 'Bi-weekly bonus based on team volume tiers (5% at 500 up to 25% at 100,000+ volume).',
        ],
        'team_performance_bonus' => [
            'title' => 'Team Performance Bonus',
            'contract' => 'teamPerformanceBonus',
            'description' => 'One-time milestone bonuses for 4 qualifying legs (40% largest leg, 20% each next 3 legs).',
        ],
        'monthly_loyalty_bonus' => [
            'title' => 'Monthly Loyalty Bonus',
            'contract' => 'monthlyLoyaltyBonus',
            'description' => 'Equal share of 5% company-wide business pool for members with 3,000+ direct monthly business.',
        ],
        'royalty_bonus' => [
            'title' => 'Royalty Bonus',
            'contract' => 'royaltyBonus',
            'description' => '15-month equal share of 5% global turnover pool after hitting 5,000 direct monthly business.',
        ],
        'team_development_bonus' => [
            'title' => 'Team Development Bonus',
            'contract' => 'teamDevelopmentBonus',
            'description' => 'Extra 5% on eligible team bonuses for 12 months with 3 direct legs of 5,000+ business each.',
        ],
    ];

    /**
     * Streams paid from a monthly pool, shown on their page from the contract:
     * the contract key and its function counting a month's registrations.
     */
    protected const MONTHLY_POOLS = [
        'monthly_loyalty_bonus' => ['contract' => 'monthlyLoyaltyBonus', 'count' => 'qualifierCount'],
        'royalty_bonus' => ['contract' => 'royaltyBonus', 'count' => 'eligibleCount'],
    ];

    public function __invoke(Request $request, ?string $stream = null): View
    {
        // Old links used ?stream=...; 'all' is the overview.
        $stream ??= $request->query('stream');
        $stream = isset(self::STREAMS[$stream]) ? $stream : null;

        $summaries = $this->summaries();

        $transactions = WalletTransaction::with('user')
            ->where('type', 'credit')
            ->when($stream,
                fn ($q) => $q->where('reason', $stream),
                fn ($q) => $q->whereIn('reason', array_keys(self::STREAMS)))
            ->orderByDesc('id')
            ->paginate($stream ? self::PER_PAGE : self::OVERVIEW_PER_PAGE)
            ->withQueryString();

        if ($stream === null) {
            return view('admin.incomes.index', [
                'summaries' => $summaries,
                'grandTotal' => array_sum(array_column($summaries, 'total')),
                'transactions' => $transactions,
            ]);
        }

        $contract = self::STREAMS[$stream]['contract'];

        return view('admin.incomes.stream', [
            'stream' => $stream,
            'meta' => self::STREAMS[$stream],
            'summary' => $summaries[$stream],
            'extraStat' => $this->extraStat($stream),
            'topEarners' => WalletTransaction::with('user')
                ->where('type', 'credit')
                ->where('reason', $stream)
                ->groupBy('user_id')
                ->selectRaw('user_id, SUM(amount) as total, COUNT(*) as credits')
                ->orderByDesc('total')
                ->take(self::TOP_EARNERS)
                ->get(),
            'transactions' => $transactions,
            'contractAddress' => Chain::contracts()[$contract] ?? null,
            'monthlyPool' => Chain::enabled() && Chain::isDeployed() ? (self::MONTHLY_POOLS[$stream] ?? null) : null,
            'explorerUrl' => config('blockchain.chain.explorer_url'),
        ]);
    }

    /**
     * Totals per stream: amount, credit count, distinct earners and average.
     *
     * @return array<string, array{key: string, title: string, contract: string, total: float, count: int, earners: int, avg: float}>
     */
    protected function summaries(): array
    {
        $aggregates = WalletTransaction::where('type', 'credit')
            ->selectRaw('reason, SUM(amount) as total_amount, COUNT(*) as transaction_count, COUNT(DISTINCT user_id) as earner_count')
            ->groupBy('reason')
            ->get()
            ->keyBy('reason');

        $summaries = [];

        foreach (self::STREAMS as $key => $meta) {
            $total = (float) ($aggregates[$key]->total_amount ?? 0);
            $count = (int) ($aggregates[$key]->transaction_count ?? 0);

            $summaries[$key] = [
                'key' => $key,
                'title' => $meta['title'],
                'contract' => $meta['contract'],
                'total' => $total,
                'count' => $count,
                'earners' => (int) ($aggregates[$key]->earner_count ?? 0),
                'avg' => $count > 0 ? $total / $count : 0.0,
            ];
        }

        return $summaries;
    }

    /**
     * The stream's own record count, shown as an extra tile on its page.
     *
     * @return array{label: string, value: int, hint: string}
     */
    protected function extraStat(string $stream): array
    {
        return match ($stream) {
            'daily_nft_bonus' => [
                'label' => 'Active NFTs',
                'value' => UserNft::where('status', 'active')->count(),
                'hint' => UserNft::where('status', 'completed')->count().' completed (4X)',
            ],
            'referral_income' => ['label' => 'Referral Records', 'value' => ReferralIncome::count(), 'hint' => 'Per-level referral entries'],
            'team_building_benefit' => ['label' => 'Team Building Records', 'value' => TeamBuildingBenefit::count(), 'hint' => 'Per-generation entries'],
            'bonus_booster' => ['label' => 'Qualified Periods', 'value' => BonusBooster::where('qualified', true)->count(), 'hint' => '15-day periods that paid out'],
            'team_performance_bonus' => ['label' => 'Performance Awards', 'value' => TeamPerformanceBonus::count(), 'hint' => 'Team targets reached'],
            'monthly_loyalty_bonus' => ['label' => 'Monthly Qualifiers', 'value' => MonthlyLoyaltyQualification::where('qualified', true)->count(), 'hint' => 'Member-months that qualified'],
            'royalty_bonus' => ['label' => 'Royalty Qualifiers', 'value' => RoyaltyQualification::count(), 'hint' => 'Members in a 15-month royalty'],
            'team_development_bonus' => ['label' => 'Development Qualifiers', 'value' => TeamDevelopmentQualification::count(), 'hint' => 'Members in a 12-month bonus'],
        };
    }
}
