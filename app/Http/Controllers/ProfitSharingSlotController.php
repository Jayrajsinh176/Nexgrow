<?php

namespace App\Http\Controllers;

use App\Models\ProfitSharingSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfitSharingSlotController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $totalSlots = (int) config('profit_sharing_slot.total_slots');
        $sold = ProfitSharingSlot::count();

        $slots = $user->profitSharingSlots->map(function (ProfitSharingSlot $slot) {
            $slot->setAttribute('days_held', $slot->daysHeld());
            $slot->setAttribute('matured', $slot->isMatured());

            if ($slot->status === 'active') {
                $slot->setAttribute('projected', $slot->projectedWithdrawal());
            }

            return $slot;
        });

        return view('profit-sharing-slot', [
            'slotAmount' => (string) config('profit_sharing_slot.slot_amount'),
            'totalSlots' => $totalSlots,
            'sold' => $sold,
            'remaining' => max(0, $totalSlots - $sold),
            'slots' => $slots,
            'alreadyOwnsSlot' => $slots->isNotEmpty(),
        ]);
    }

    public function purchase(Request $request): RedirectResponse
    {
        try {
            $request->user()->purchaseProfitSharingSlot();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['slot' => $e->getMessage()]);
        }

        return redirect()->route('profit-sharing-slot.index')->with('status', 'Profit-Sharing Slot purchased.');
    }

    public function withdraw(Request $request, ProfitSharingSlot $slot): RedirectResponse
    {
        try {
            $withdrawn = $request->user()->withdrawProfitSharingSlot($slot);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['slot' => $e->getMessage()]);
        }

        return redirect()->route('profit-sharing-slot.index')
            ->with('status', "Slot withdrawn: {$withdrawn->withdrawal_amount} credited ({$withdrawn->penalty_percent}% penalty).");
    }
}
