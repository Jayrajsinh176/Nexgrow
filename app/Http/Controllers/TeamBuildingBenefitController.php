<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamBuildingBenefitController extends Controller
{
    protected const PER_PAGE = 10;

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $maxLevels = (int) config('team_building_benefits.max_levels');
        $minimumDirects = (int) config('team_building_benefits.minimum_direct_referrals');
        $directCount = $user->referrals()->count();
        $unlockedLevels = $user->unlockedTeamBuildingLevels();
        $hasActiveNft = $user->activeNft() !== null;

        $levels = [];

        for ($level = 1; $level <= $maxLevels; $level++) {
            $tier = collect(config('team_building_benefits.tiers'))
                ->first(fn ($t) => $level >= $t['from'] && $level <= $t['to']);

            // Mirrors User::unlockedTeamBuildingLevels(): the minimum opens
            // levels 1..minimum at once, then one level per extra direct.
            $required = max($minimumDirects, $level);

            $levels[] = [
                'level' => $level,
                'percentage' => $tier['percentage'] ?? 0,
                'required' => $required,
                'unlocked' => $level <= $unlockedLevels,
                'shortfall' => max(0, $required - $directCount),
            ];
        }

        return view('team-building-benefits', [
            'user' => $user,
            'hasActiveNft' => $hasActiveNft,
            'directCount' => $directCount,
            'minimumDirects' => $minimumDirects,
            'unlockedLevels' => $unlockedLevels,
            'maxLevels' => $maxLevels,
            'levels' => $levels,
            'nextLevel' => $levels[$unlockedLevels] ?? null,
            'history' => $user->teamBuildingBenefitsReceived()->with('sourceUser')->orderByDesc('id')
                ->paginate(self::PER_PAGE)->fragment('history')->onEachSide(1),
            'total' => $user->teamBuildingBenefitsReceived()->sum('amount'),
        ]);
    }
}
