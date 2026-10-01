<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class RoyaltyBonusController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $threshold = (string) config('royalty_bonus.direct_business_threshold');
        $qualification = $user->royaltyQualification;

        $periodStart = now()->startOfMonth();
        $currentBusiness = $user->directBusinessVolume($periodStart, now());
        $progress = min(100, max(0, (float) bcdiv(bcmul($currentBusiness, '100', 4), $threshold, 2)));

        $windowActive = $qualification !== null
            && now()->between($qualification->window_start, $qualification->window_end);

        return view('royalty-bonus', [
            'threshold' => $threshold,
            'qualification' => $qualification,
            'windowActive' => $windowActive,
            'periodStart' => $periodStart,
            'currentBusiness' => $currentBusiness,
            'progress' => $progress,
        ]);
    }
}
