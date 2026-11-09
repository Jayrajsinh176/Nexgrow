@extends('layouts.dashboard', ['title' => $income['title'], 'dark' => true])

{{-- Chain mode: one income type read from its contract. Filled in by
     resources/js/nexgrow/income.js (widget "income"). --}}

@php
    $coin = '<span class="nx-coin block h-4 w-4"><img src="'.asset('coin_nexadon.png').'" alt="" class="h-full w-full scale-[1.06] object-cover"></span>';

    $stats = [
        ['field' => 'credited', 'label' => 'Total Earned', 'hint' => 'All income credited to your wallet', 'tile' => 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300', 'icon' => 'M3 10h18M7 15h1m4 0h1m-9 4h16a2 2 0 002-2V7a2 2 0 00-2-2H3a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ['field' => 'paid', 'label' => 'Paid to Wallet', 'hint' => 'Received in payment rounds', 'tile' => 'border-nx-gold/60 bg-nx-gold/10 text-nx-gold', 'icon' => 'M7 17L17 7m0 0H9m8 0v8'],
        ['field' => 'pending', 'label' => 'Pending', 'hint' => 'Paid in the next payment round', 'tile' => 'border-sky-400/50 bg-sky-400/10 text-sky-300', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
@endphp

@section('content')
<div class="space-y-5 text-slate-200">
    @if (! $deployed)
        <div class="rounded-xl border border-amber-400/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            The income contracts are not deployed on this network yet.
        </div>
    @endif

    @if (! $user->wallet_address)
        @include('partials.chain-wallet')
    @endif

    <div class="space-y-5" data-nx-chain="income" data-nx-contract="{{ $income['contract'] }}">
        {{-- Hero --}}
        <section class="nx-panel nx-panel-gold overflow-hidden">
            <div class="nx-stars pointer-events-none absolute inset-0"></div>
            <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>

            <div class="relative p-6 sm:px-8">
                <p class="font-medium text-white">Total {{ $income['title'] }} Earned</p>
                <p class="nx-gold-text mt-1 text-3xl font-bold break-words sm:text-5xl"><span data-nx-field="credited-hero">0.00</span> <span class="text-2xl">NXD</span></p>
                <p class="mt-2 max-w-3xl text-sm text-slate-300">{{ $income['description'] }}</p>
                <p data-nx-field="status" class="mt-3 hidden text-sm font-medium text-nx-gold"></p>
            </div>
        </section>

        {{-- Stat cards --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach ($stats as $stat)
                <div class="nx-panel flex items-center gap-4 p-5">
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border {{ $stat['tile'] }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}" /></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-300">{{ $stat['label'] }}</p>
                        <p class="mt-1 flex items-center gap-2.5 text-2xl font-bold text-white">
                            <span data-nx-field="{{ $stat['field'] }}">0.00</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-nx-gold/50 bg-nx-gold/10 px-2 py-0.5 text-xs font-semibold text-nx-gold">{!! $coin !!} NXD</span>
                        </p>
                        <p class="mt-1 text-xs text-slate-400">{{ $stat['hint'] }}</p>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- Credit history --}}
        <section class="nx-panel overflow-hidden">
            <h3 class="border-b border-white/10 px-6 py-4 text-lg font-semibold text-white">Income History</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-160 text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-slate-200">
                            @foreach ($income['columns'] as $column)
                                <th class="px-6 py-3 font-semibold">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody data-nx-field="credits" class="divide-y divide-white/5"></tbody>
                </table>
            </div>
            <p data-nx-field="credits-empty" class="px-6 py-6 text-sm text-slate-400">No income yet.</p>
        </section>

        {{-- Payouts --}}
        <section class="nx-panel overflow-hidden">
            <h3 class="border-b border-white/10 px-6 py-4 text-lg font-semibold text-white">Payouts to Your Wallet</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-160 text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-slate-200">
                            <th class="px-6 py-3 font-semibold">Date</th>
                            <th class="px-6 py-3 font-semibold">Round</th>
                            <th class="px-6 py-3 font-semibold">Paid</th>
                            <th class="px-6 py-3 font-semibold">Carried forward</th>
                        </tr>
                    </thead>
                    <tbody data-nx-field="payouts" class="divide-y divide-white/5"></tbody>
                </table>
            </div>
            <p data-nx-field="payouts-empty" class="px-6 py-6 text-sm text-slate-400">No payouts yet.</p>
        </section>

        <p data-nx-field="message" class="hidden text-sm"></p>
    </div>
</div>
@endsection
