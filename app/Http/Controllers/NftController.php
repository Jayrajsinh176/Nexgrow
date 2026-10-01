<?php

namespace App\Http\Controllers;

use App\Models\NftPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NftController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('nft.index', [
            'packages' => NftPackage::where('is_active', true)->orderBy('sort_order')->get(),
            'activeNft' => $user->activeNft(),
            'pastNfts' => $user->nfts()->where('status', 'completed')->get(),
            'balance' => $user->token_balance,
        ]);
    }

    public function purchase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nft_package_id' => ['required', 'integer', 'exists:nft_packages,id'],
        ]);

        $package = NftPackage::findOrFail($validated['nft_package_id']);

        try {
            $request->user()->purchaseNft($package);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['nft_package_id' => $e->getMessage()]);
        }

        return redirect()->route('nft.index')->with('status', "You purchased {$package->name}.");
    }
}
