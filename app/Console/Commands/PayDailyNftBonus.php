<?php

namespace App\Console\Commands;

use App\Models\UserNft;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PayDailyNftBonus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:pay-daily-nft-bonus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Credit today's daily bonus to every active NFT, capped at each NFT's 4X payout limit.";

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $nfts = UserNft::with(['user', 'nftPackage'])
            ->where('status', 'active')
            ->get();

        $paid = 0;
        $skipped = 0;

        foreach ($nfts as $nft) {
            if ($nft->last_paid_at?->isToday()) {
                $skipped++;

                continue;
            }

            $remaining = $nft->remainingCap();

            if (bccomp($remaining, '0', 2) <= 0) {
                continue;
            }

            $payout = bccomp($remaining, $nft->daily_bonus, 2) < 0 ? $remaining : $nft->daily_bonus;

            DB::transaction(function () use ($nft, $payout) {
                $nft->user->creditTokens((float) $payout, 'daily_bonus', "Daily bonus for {$nft->nftPackage->name}");
                $nft->user->payReferralIncomeUpline($payout, $nft);
                $nft->user->payTeamBuildingBenefitsUpline($payout, $nft);

                $nft->total_paid_out = bcadd($nft->total_paid_out, $payout, 2);
                $nft->last_paid_at = now()->toDateString();

                if (bccomp($nft->total_paid_out, $nft->cap_amount, 2) >= 0) {
                    $nft->status = 'completed';
                    $nft->completed_at = now();
                }

                $nft->save();
            });

            $paid++;
        }

        $this->info("Paid {$paid} NFT(s). Skipped {$skipped} already paid today.");
    }
}
