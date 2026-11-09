<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Chain;
use App\Support\KeeperStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\Process\PhpExecutableFinder;

class BlockchainManagementController extends Controller
{
    public function index(): View
    {
        $chainConfig = config('blockchain.chain');
        $keeperConfig = config('blockchain.keeper');
        $contracts = Chain::contracts();
        $isDeployed = Chain::isDeployed();

        $contractRoles = [
            'paymentToken' => 'ERC-20 Token (Payment currency: USDT / tNEX)',
            'treasury' => 'Treasury (Collects 100% of NFT sales)',
            'nft' => 'NexgrowNFT (ERC-721 package minting)',
            'manager' => 'NexgrowManager (Module directory & 4X cap checks)',
            'rewardPool' => 'RewardPool (Holds income budget & executes releases)',
            'liquidityPool' => 'LiquidityPool (Platform liquidity reserve)',
            'nftIncome' => 'NFTIncome (Daily 0.30–1.00 NXD ROI payouts)',
            'referralIncome' => 'ReferralIncome (Unlimited level upline commissions)',
            'teamBuildingIncome' => 'TeamBuildingIncome (20-generation benefits)',
            'teamBusiness' => 'TeamBusiness (Downline purchase volume tracker)',
            'bonusBooster' => 'BonusBooster (15-day volume booster)',
            'teamPerformanceBonus' => 'TeamPerformanceBonus (4-leg milestone rewards)',
            'monthlyLoyaltyBonus' => 'MonthlyLoyaltyBonus (5% monthly direct pool)',
            'royaltyBonus' => 'RoyaltyBonus (5% global turnover 15-month pool)',
            'teamDevelopmentBonus' => 'TeamDevelopmentBonus (5% extra team bonus for 12 months)',
            'profitSharingSlot' => 'ProfitSharingSlot ($3,000 slots)',
        ];

        return view('admin.blockchain.index', [
            'chainConfig' => $chainConfig,
            'keeperConfig' => $keeperConfig,
            'contracts' => $contracts,
            'contractRoles' => $contractRoles,
            'isDeployed' => $isDeployed,
            'mode' => config('blockchain.mode'),
            'keeperTasks' => self::KEEPER_TASKS,
            'keeperStatus' => KeeperStatus::get(),
            'keeperRunning' => KeeperStatus::isRunning(),
        ]);
    }

    /**
     * Keeper task sets offered on the page (value => label).
     */
    public const KEEPER_TASKS = [
        'queues,development,performance,booster,monthly' => 'All Routine Tasks (Recommended)',
        'queues' => 'Queues Only (Referral, Team Building, Team Business)',
        'booster' => '15-Day Booster Settlement Only',
        'monthly' => 'Monthly Loyalty & Royalty Only',
        'performance,development' => 'Performance & Development Qualifications Only',
        'claims' => 'Process Claims for Active Rounds',
    ];

    /**
     * Starts the keeper in the background and returns at once: a run compiles
     * the Hardhat project and sends transactions, which takes longer than a
     * web request may last. The page shows the run's progress (KeeperStatus).
     */
    public function runKeeper(Request $request): RedirectResponse
    {
        $tasks = $request->validate([
            'tasks' => ['required', 'string', Rule::in(array_keys(self::KEEPER_TASKS))],
        ])['tasks'];

        if (KeeperStatus::isRunning()) {
            return back()->withErrors(['keeper' => 'The keeper is already running. Wait for it to finish.']);
        }

        KeeperStatus::started($tasks, 'manual');

        $php = (new PhpExecutableFinder)->find(false) ?: 'php';
        $command = escapeshellarg($php).' artisan app:run-blockchain-keeper --manual --tasks='.escapeshellarg($tasks);

        // Detach, with output to the null device, so this request does not wait for it.
        $result = Process::path(base_path())->run(PHP_OS_FAMILY === 'Windows'
            ? 'start "" /B '.$command.' > NUL 2>&1'
            : 'nohup '.$command.' > /dev/null 2>&1 &');

        if ($result->failed()) {
            KeeperStatus::finished(false, 'Could not start the keeper: '.trim($result->errorOutput() ?: $result->output()));

            return back()->withErrors(['keeper' => 'Could not start the keeper. See Last run below.']);
        }

        return back()->with('status', 'Keeper started. Last run below updates when it finishes.');
    }
}

