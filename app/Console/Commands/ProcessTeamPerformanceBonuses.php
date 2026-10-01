<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ProcessTeamPerformanceBonuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-team-performance-bonuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Award every user any Team Performance Bonus tiers their team's lifetime business volume newly qualifies for.";

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $users = User::all();
        $awarded = 0;

        foreach ($users as $user) {
            $awarded += $user->processTeamPerformanceBonuses()->count();
        }

        $this->info("Processed {$users->count()} user(s): {$awarded} tier bonus(es) awarded.");
    }
}
