<?php

namespace App\Http\Controllers;

use App\Models\ProfitSharingSlot;
use App\Support\Chain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfitSharingSlotController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if (Chain::enabled()) {
            return view('profit-sharing-slot-chain', [
                'user' => $user,
                'deployed' => Chain::isDeployed(),
            ]);
        }

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
        if ($refused = $this->refuseInChainMode()) {
            return $refused;
        }

        try {
            $request->user()->purchaseProfitSharingSlot();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['slot' => $e->getMessage()]);
        }

        return redirect()->route('profit-sharing-slot.index')->with('status', 'Profit-Sharing Slot purchased.');
    }

    public function withdraw(Request $request, ProfitSharingSlot $slot): RedirectResponse
    {
        if ($refused = $this->refuseInChainMode()) {
            return $refused;
        }

        try {
            $withdrawn = $request->user()->withdrawProfitSharingSlot($slot);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['slot' => $e->getMessage()]);
        }

        return redirect()->route('profit-sharing-slot.index')
            ->with('status', "Slot withdrawn: {$withdrawn->withdrawal_amount} credited ({$withdrawn->penalty_percent}% penalty).");
    }

    /**
     * In chain mode slots are bought and withdrawn with the user's wallet.
     */
    protected function refuseInChainMode(): ?RedirectResponse
    {
        if (! Chain::enabled()) {
            return null;
        }

        return redirect()->route('profit-sharing-slot.index')
            ->withErrors(['slot' => 'Slots are bought and withdrawn with your wallet on this page.']);
    }
}
