<?php

namespace App\Http\Controllers;

use App\Support\Chain;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamDevelopmentBonusController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if (Chain::enabled()) {
            return Chain::incomeView('team-development-bonus', $user);
        }
        $qualification = $user->teamDevelopmentQualification;
        $status = $user->teamDevelopmentStatus();

        $windowActive = $qualification !== null
            && now()->between($qualification->window_start, $qualification->window_end);

        return view('team-development-bonus', [
            'qualification' => $qualification,
            'windowActive' => $windowActive,
            'requiredPerTeam' => $status['required_per_team'],
            'teams' => $status['teams'],
        ]);
    }
}
