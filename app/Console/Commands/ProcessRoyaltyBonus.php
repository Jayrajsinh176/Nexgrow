<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ProcessRoyaltyBonus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-royalty-bonus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check every user against the Royalty Bonus one-time lifetime qualification for the current calendar month, opening their eligibility window if newly qualified.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $users = User::all();
        $newlyQualified = 0;

        foreach ($users as $user) {
            if ($user->processRoyaltyQualification($periodStart, $periodEnd) !== null) {
                $newlyQualified++;
            }
        }

        $this->info("Checked {$users->count()} user(s) for {$periodStart->format('F Y')}: {$newlyQualified} newly qualified.");
    }
}
