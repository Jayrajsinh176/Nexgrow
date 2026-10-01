<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamPerformanceBonusController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $awardedTargets = $user->teamPerformanceBonuses()->pluck('team_target');
        $nextTier = null;

        $tiers = collect(config('team_performance_bonus.tiers'))->map(function (array $tier) use ($user, $awardedTargets, &$nextTier) {
            $achieved = $awardedTargets->contains(
                fn ($awardedTarget) => bccomp((string) $awardedTarget, (string) $tier['target'], 2) === 0
            );

            $status = $user->teamPerformanceTierStatus($tier);

            if (! $achieved && $nextTier === null) {
                $nextTier = $status;
            }

            $tier['achieved'] = $achieved;
            $tier['progress'] = collect($status['legs'])->min('progress');

            return $tier;
        });

        return view('team-performance-bonus', [
            'tiers' => $tiers,
            'nextTier' => $nextTier,
            'totalBonus' => $user->teamPerformanceBonuses()->sum('bonus_amount'),
            'history' => $user->teamPerformanceBonuses()->paginate(20),
        ]);
    }
}
