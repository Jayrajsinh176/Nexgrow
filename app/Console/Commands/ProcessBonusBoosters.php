<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ProcessBonusBoosters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-bonus-boosters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Close out every user's 15-Day Bonus Booster window that has come due, crediting the tiered bonus if their team's business qualifies.";

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $users = User::where('next_booster_due_at', '<=', now())->get();

        $qualified = 0;
        $notQualified = 0;

        foreach ($users as $user) {
            $record = $user->processBoosterCycle();

            $record->qualified ? $qualified++ : $notQualified++;
        }

        $this->info("Processed {$users->count()} booster cycle(s): {$qualified} qualified, {$notQualified} did not.");
    }
}
