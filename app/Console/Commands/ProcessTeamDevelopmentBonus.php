<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ProcessTeamDevelopmentBonus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-team-development-bonus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Check every user against the Team Development Bonus one-time lifetime qualification (3 teams each reaching the required business), opening their 12-month window if newly qualified.";

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $users = User::all();
        $newlyQualified = 0;

        foreach ($users as $user) {
            if ($user->processTeamDevelopmentQualification() !== null) {
                $newlyQualified++;
            }
        }

        $this->info("Checked {$users->count()} user(s): {$newlyQualified} newly qualified.");
    }
}
