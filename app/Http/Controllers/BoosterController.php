<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoosterController extends Controller
{
    protected const TIERS = [
        ['business' => 500, 'percentage' => 5],
        ['business' => 1000, 'percentage' => 7],
        ['business' => 2500, 'percentage' => 10],
        ['business' => 5000, 'percentage' => 12],
        ['business' => 10000, 'percentage' => 15],
        ['business' => 25000, 'percentage' => 17],
        ['business' => 50000, 'percentage' => 20],
        ['business' => 100000, 'percentage' => 25],
    ];

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $currentBusiness = $user->teamBusinessVolume($user->booster_period_started_at, now());
        $currentPercentage = User::boosterPercentageForBusiness($currentBusiness);

        // Progress bar runs from the tier already reached to the next one.
        $tiers = collect(self::TIERS);
        $currentTier = $tiers->last(fn ($tier) => (float) $currentBusiness >= $tier['business']);
        $nextTier = $tiers->first(fn ($tier) => (float) $currentBusiness < $tier['business']);
        $floor = $currentTier['business'] ?? 0;
        $progress = $nextTier
            ? min(100, max(0, ((float) $currentBusiness - $floor) / ($nextTier['business'] - $floor) * 100))
            : 100;

        return view('booster', [
            'tiers' => self::TIERS,
            'periodStart' => $user->booster_period_started_at,
            'periodEnd' => $user->next_booster_due_at,
            'currentBusiness' => $currentBusiness,
            'currentPercentage' => $currentPercentage,
            'currentTier' => $currentTier,
            'nextTier' => $nextTier,
            'progress' => $progress,
            'estimatedBonus' => bcdiv(bcmul($currentBusiness, $currentPercentage, 4), '100', 2),
            'history' => $user->bonusBoosters()->orderByDesc('id')->paginate(10)->fragment('history')->onEachSide(1),
        ]);
    }
}
