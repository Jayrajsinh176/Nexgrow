@extends('layouts.dashboard', ['title' => 'Team Performance Bonus', 'dark' => true])

@php
    $nxd = fn ($value, $dec = 2) => number_format((float) $value, $dec);
    $checkIcon = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
    $clockIcon = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
    $heroImage = file_exists(public_path('team-bonus-hero.png')) ? asset('team-bonus-hero.png') : null;
@endphp

@section('content')
<div class="space-y-5 text-slate-200">

    {{-- Top row --}}
    <section class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_340px]">
        {{-- Next target --}}
        <div class="nx-panel nx-panel-gold overflow-hidden">
            <div class="nx-stars pointer-events-none absolute inset-0"></div>
            <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
            <div class="pointer-events-none absolute -right-10 top-1/2 h-64 w-64 -translate-y-1/2 rounded-full bg-nx-gold/15 blur-3xl"></div>
            @if ($heroImage)
                <img src="{{ $heroImage }}" alt="" class="pointer-events-none absolute right-0 bottom-0 hidden h-full max-w-[55%] object-contain object-right-bottom md:block">
            @endif

            <div class="relative p-5 sm:px-6">
                @if ($nextTier)
                    <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-nx-gold/60 bg-nx-gold/10 text-nx-gold shadow-[0_0_20px_-8px] shadow-nx-gold">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/>
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-white">Next Team Target</p>
                            <p class="nx-gold-text mt-1 text-3xl font-bold break-words sm:text-5xl">{{ $nxd($nextTier['target'], 0) }} <span class="text-2xl">NXD</span></p>
                        </div>
                    </div>
                    <p class="mt-3 max-w-2xl text-xs text-slate-300">
                        Each leg below must independently reach its own share of the target &mdash; a leg's surplus can't cover another leg's shortfall.
                    </p>
                @else
                    <p class="py-4 text-lg font-semibold text-emerald-400">All Team Performance Bonus tiers have been achieved.</p>
                @endif
            </div>
        </div>

        {{-- Total bonus --}}
        <div class="nx-panel nx-panel-gold overflow-hidden">
            <div class="nx-stars pointer-events-none absolute inset-0"></div>
            <div class="relative flex h-full items-center gap-4 p-5">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-nx-gold/60 bg-nx-gold/10 shadow-[0_0_20px_-8px] shadow-nx-gold">
                    <span class="nx-coin nx-coin-static block h-12 w-12"><img src="{{ asset('coin_nexadon.png') }}" alt="" class="h-full w-full scale-[1.06] object-cover"></span>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-white">Total Bonus</p>
                    <p class="mt-1 text-3xl font-bold text-emerald-400">{{ $nxd($totalBonus, 0) }} <span class="text-2xl">NXD</span></p>
                </div>
            </div>
        </div>
    </section>

    {{-- Qualifying legs --}}
    @if ($nextTier)
        <section class="nx-panel p-5">
            <h3 class="mb-4 flex items-center gap-3 text-lg font-semibold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M9 11a3 3 0 100-6 3 3 0 000 6zm6 0a3 3 0 100-6 3 3 0 000 6zM9 13c-2.7 0-6 1.3-6 4v2h12v-2c0-2.7-3.3-4-6-4zm6 0c-.4 0-.8 0-1.2.1 1.2.9 2.2 2.2 2.2 3.9v2h5v-2c0-2.7-3.3-4-6-4z"/></svg>
                Qualifying Legs for {{ $nxd($nextTier['target'], 0) }} NXD
            </h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($nextTier['legs'] as $leg)
                    @php $pct = min(max($leg['progress'], 0), 100); @endphp
                    <div class="rounded-xl border p-4 {{ $leg['met']
                        ? 'border-emerald-400 bg-emerald-400/10 shadow-[0_0_18px_-4px] shadow-emerald-400'
                        : 'border-white/20 bg-black/30' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm text-slate-300">LEG {{ $loop->iteration }}</p>
                                <p class="truncate text-sm font-semibold text-white">{{ $leg['name'] ?? 'No leg yet' }}</p>
                            </div>
                            <span class="rounded-full border px-2.5 py-0.5 text-xs font-bold {{ $leg['met']
                                ? 'border-emerald-400 bg-emerald-400 text-black'
                                : 'border-white/50 text-white' }}">
                                {{ $nxd($leg['weight'], 0) }}%
                            </span>
                        </div>

                        <p class="mt-3 text-xs text-slate-300">Required: {{ $nxd($leg['required']) }}</p>
                        <p class="text-xs text-slate-300">Volume: {{ $nxd($leg['business_volume']) }}</p>

                        <div class="mt-3 flex items-center gap-3">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full {{ $leg['met'] ? 'bg-emerald-400' : 'bg-slate-300' }}" style="width: {{ $pct }}%"></div>
                            </div>
                            @if ($leg['met'])
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 shrink-0 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $checkIcon }}"/></svg>
                            @endif
                        </div>
                        <p class="mt-1 text-sm font-semibold {{ $leg['met'] ? 'text-emerald-400' : 'text-white' }}">
                            {{ $nxd($leg['progress'], 1) }}% {{ $leg['met'] ? '(met)' : '' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Tiers --}}
    <section class="nx-panel overflow-hidden">
        <div class="px-6 pt-5 pb-3">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M4 20V10h4v10H4zm6 0V4h4v16h-4zm6 0v-7h4v7h-4z"/></svg>
                Team Target Tiers
            </h3>
        </div>

        <div class="overflow-x-auto px-3 pb-3">
            <table class="w-full min-w-225 text-left text-sm">
                <thead>
                    <tr class="bg-emerald-950/60 text-slate-200">
                        <th class="px-4 py-3 font-medium">Team Target (NXD)</th>
                        <th class="px-4 py-3 font-medium">Bonus (NXD)</th>
                        <th class="px-4 py-3 font-medium">Progress</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tiers as $tier)
                        @php $pct = min(max($tier['progress'], 0), 100); @endphp
                        <tr class="{{ $tier['achieved']
                            ? 'border-y border-nx-gold/60 bg-nx-gold/20 text-nx-gold'
                            : 'border-b border-white/5 text-slate-200 transition hover:bg-white/3' }}">
                            <td class="px-4 py-2.5">{{ $nxd($tier['target'], 0) }}</td>
                            <td class="px-4 py-2.5">{{ $nxd($tier['bonus'], 0) }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <div class="h-2 w-44 overflow-hidden rounded-full bg-white/10">
                                        <div class="h-full rounded-full {{ $tier['achieved'] ? 'bg-linear-to-r from-nx-gold to-emerald-400' : 'bg-nx-gold' }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs {{ $tier['achieved'] ? 'text-nx-gold' : 'text-slate-100' }}">{{ $nxd($tier['progress'], 1) }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($tier['achieved'])
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/10 px-2.5 py-0.5 text-xs font-medium text-emerald-400 ring-1 ring-emerald-400/50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $checkIcon }}"/></svg>
                                        Achieved
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-black/40 px-2.5 py-0.5 text-xs font-medium text-white ring-1 ring-white/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $clockIcon }}"/></svg>
                                        Pending
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="px-6 pb-4 text-xs text-slate-400">Each tier is a one-time lifetime milestone &mdash; once achieved, it isn't paid again.</p>
    </section>

    {{-- Bonus history --}}
    <section id="history" class="nx-panel nx-panel-gold scroll-mt-28 overflow-hidden">
        <div class="px-6 pt-5 pb-3">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3M3.05 11a9 9 0 11.5 4M3 4v5h5" /></svg>
                Bonus History
            </h3>
        </div>

        @if ($history->isEmpty())
            <p class="px-6 py-10 text-center text-sm text-slate-400">No Team Performance Bonus paid out yet.</p>
        @else
            <div class="overflow-x-auto px-3 pb-3">
                <table class="w-full min-w-225 text-left text-sm">
                    <thead>
                        <tr class="bg-emerald-950/60 text-slate-200">
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Team Target (NXD)</th>
                            <th class="px-4 py-3 font-medium">Team Business (NXD)</th>
                            <th class="px-4 py-3 font-medium">Bonus (NXD)</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($history as $record)
                            <tr class="text-slate-200 transition hover:bg-white/3">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $record->created_at->format('d M Y, h:i A') }}</td>
                                <td class="px-4 py-3">{{ $nxd($record->team_target, 0) }}</td>
                                <td class="px-4 py-3">{{ $nxd($record->qualifying_business) }}</td>
                                <td class="px-4 py-3 font-semibold text-emerald-400">+{{ $nxd($record->bonus_amount) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/10 px-2.5 py-0.5 text-xs font-medium text-emerald-400 ring-1 ring-emerald-400/50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $checkIcon }}"/></svg>
                                        Credited
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-6 py-4">
                <p class="text-sm text-slate-400">
                    Showing <span class="text-white">{{ $history->firstItem() }}</span>–<span class="text-white">{{ $history->lastItem() }}</span>
                    of <span class="text-white">{{ $history->total() }}</span> entries
                </p>
                {{ $history->fragment('history')->links('pagination.nx') }}
            </div>
        @endif
    </section>
</div>
@endsection