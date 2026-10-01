<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class MonthlyLoyaltyBonusController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();
        $threshold = (string) config('monthly_loyalty_bonus.direct_business_threshold');

        $currentBusiness = $user->directBusinessVolume($periodStart, now());
        $qualified = bccomp($currentBusiness, $threshold, 2) >= 0;

        return view('monthly-loyalty-bonus', [
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'threshold' => $threshold,
            'currentBusiness' => $currentBusiness,
            'qualified' => $qualified,
            'progress' => min(100, (float) bcdiv(bcmul($currentBusiness, '100', 4), $threshold, 2)),
            'history' => $user->monthlyLoyaltyQualifications()->paginate(20),
        ]);
    }
}
