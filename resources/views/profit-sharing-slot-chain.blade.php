@extends('layouts.dashboard', ['title' => 'Profit-Sharing Slot', 'dark' => true])

{{-- Chain mode: slots are bought and withdrawn with the user's wallet
     through the ProfitSharingSlot contract. Widget "slot" in
     resources/js/nexgrow/slot.js. --}}

@php
    $terms = [
        ['label' => 'Slot price', 'value' => '3,000 NXD'],
        ['label' => 'Total slots', 'value' => '100 (one per wallet)'],
        ['label' => 'Earns until', 'value' => '10X profit (30,000) or 750 days'],
        ['label' => 'Early withdrawal', 'value' => '50% before 180 days, 25% before 360, 10% before 540'],
    ];
@endphp

@section('content')
<div class="space-y-5 text-slate-200">
    @error('slot')
        <div class="rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ $message }}</div>
    @enderror

    @if (! $deployed)
        <div class="rounded-xl border border-amber-400/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            The slot contract is not deployed on this network yet.
        </div>
    @endif

    @if (! $user->wallet_address)
        @include('partials.chain-wallet')
    @endif

    <div class="space-y-5" data-nx-chain="slot">
        {{-- Terms --}}
        <section class="nx-panel nx-panel-gold overflow-hidden">
            <div class="nx-stars pointer-events-none absolute inset-0"></div>
            <div class="relative p-6 sm:px-8">
                <p class="font-medium text-white">$3,000 Profit-Sharing Slot</p>
                <p class="nx-gold-text mt-1 text-3xl font-bold sm:text-4xl"><span data-nx-field="remaining">…</span> <span class="text-xl">slots left</span></p>
                <p class="mt-2 max-w-3xl text-sm text-slate-300">
                    A separate product from the NFT packages. Slot holders share the profit the project deposits, in equal parts,
                    paid together with your 3,000 when you withdraw.
                </p>

                <dl class="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($terms as $term)
                        <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                            <dt class="text-xs text-emerald-300/80">{{ $term['label'] }}</dt>
                            <dd class="mt-1 text-white">{{ $term['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        {{-- Buy --}}
        <section data-nx-field="buy-panel" class="nx-panel hidden p-5">
            <p class="text-sm text-slate-300">Your wallet does not hold a slot yet.</p>
            <button type="button" data-nx-action="buy" @disabled(! $deployed)
                class="mt-3 rounded-xl border border-nx-gold/70 bg-gradient-to-b from-amber-500/40 to-amber-900/40 px-5 py-2.5 text-sm font-semibold text-white transition hover:border-nx-gold hover:shadow-[0_0_20px_-6px] hover:shadow-nx-gold disabled:cursor-not-allowed disabled:opacity-50">
                Buy a slot with wallet &middot; 3,000 NXD
            </button>
        </section>

        {{-- The wallet's slot --}}
        <section data-nx-field="slot-panel" class="nx-panel hidden p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h3 class="text-lg font-semibold text-white">Your Slot <span class="text-sm text-slate-400">#<span data-nx-field="slot-id"></span></span></h3>
                    <span data-nx-field="slot-status" class="rounded-full border border-white/20 px-3 py-0.5 text-xs text-slate-300"></span>
                </div>
                <p class="text-sm text-slate-400">Bought <span class="text-white" data-nx-field="purchased"></span> &middot; <span class="text-white" data-nx-field="days"></span> days held</p>
            </div>

            <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-4">
                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                    <dt class="text-xs text-emerald-300/80">Profit so far</dt>
                    <dd class="mt-1 text-lg font-semibold text-white"><span data-nx-field="profit"></span> NXD</dd>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                    <dt class="text-xs text-emerald-300/80">Principal + profit</dt>
                    <dd class="mt-1 text-lg font-semibold text-white"><span data-nx-field="total"></span> NXD</dd>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                    <dt class="text-xs text-emerald-300/80">Deduction if withdrawn now</dt>
                    <dd class="mt-1 text-lg font-semibold text-white" data-nx-field="deduction"></dd>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                    <dt class="text-xs text-emerald-300/80">You would receive</dt>
                    <dd class="mt-1 text-lg font-semibold text-emerald-400"><span data-nx-field="payout"></span> NXD</dd>
                </div>
            </dl>

            <button type="button" data-nx-action="withdraw"
                class="mt-4 rounded-xl border border-rose-400/50 bg-rose-500/10 px-5 py-2.5 text-sm font-semibold text-rose-200 transition hover:border-rose-300 hover:bg-rose-500/20">
                Withdraw slot
            </button>
        </section>

        <p data-nx-field="message" class="hidden text-sm"></p>
    </div>
</div>
@endsection
