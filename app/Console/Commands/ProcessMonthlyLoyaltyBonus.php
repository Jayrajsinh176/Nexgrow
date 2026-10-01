<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ProcessMonthlyLoyaltyBonus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-monthly-loyalty-bonus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Refresh every user's Monthly Loyalty Bonus qualification for the current calendar month based on their direct referrals' business.";

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $users = User::all();
        $qualified = 0;

        foreach ($users as $user) {
            $record = $user->processMonthlyLoyaltyQualification($periodStart, $periodEnd);

            $record->qualified && $qualified++;
        }

        $this->info("Processed {$users->count()} user(s) for {$periodStart->format('F Y')}: {$qualified} qualified.");
    }
}
