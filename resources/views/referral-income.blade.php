@extends('layouts.dashboard', ['title' => 'Referral Income', 'dark' => true])

@php
    $nxd = fn ($value) => number_format((float) $value, 2);
    $coin = '<span class="nx-coin block h-4 w-4"><img src="'.asset('coin_nexadon.png').'" alt="" class="h-full w-full scale-[1.06] object-cover"></span>';

    $stats = [
        [
            'label' => 'Total Earned', 'value' => $nxd($total), 'hint' => 'All time referral income',
            'tile' => 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300',
            'icon' => 'M3 10h18M7 15h1m4 0h1m-9 4h16a2 2 0 002-2V7a2 2 0 00-2-2H3a2 2 0 00-2 2v10a2 2 0 002 2z',
        ],
        [
            'label' => 'This Month', 'value' => $nxd($thisMonth), 'hint' => 'Earned in '.now()->format('F Y'),
            'tile' => 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300',
            'icon' => 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1zm4 9h.01M12 14h.01M15 14h.01',
        ],
        [
            'label' => 'Latest Income', 'value' => $latest ? $nxd($latest->amount) : '0.00',
            'hint' => $latest?->created_at->format('d M Y, h:i A') ?? 'Nothing received yet',
            'tile' => 'border-nx-gold/60 bg-nx-gold/10 text-nx-gold',
            'icon' => 'M7 17L17 7m0 0H9m8 0v8',
        ],
    ];
@endphp

@section('content')
<div class="space-y-5 text-slate-200">

    {{-- Hero --}}
    <section class="nx-panel nx-panel-gold overflow-hidden">
        <div class="nx-stars pointer-events-none absolute inset-0"></div>
        <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
        <div class="pointer-events-none absolute -right-10 top-1/2 h-72 w-72 -translate-y-1/2 rounded-full bg-nx-gold/15 blur-3xl"></div>

        <div class="relative flex items-center justify-between gap-6 p-6 sm:px-8">
            <div class="flex min-w-0 items-center gap-4 sm:gap-6">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl sm:h-20 sm:w-20 border border-nx-gold/60 bg-nx-gold/10 text-nx-gold shadow-[0_0_20px_-8px] shadow-nx-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 sm:h-11 sm:w-11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
                <div class="min-w-0">
                    <p class="font-medium text-white">Total Referral Income Earned</p>
                    <p class="nx-gold-text mt-1 text-3xl font-bold break-words sm:text-5xl">{{ $nxd($total) }} <span class="text-2xl">NXD</span></p>
                    <p class="mt-2 text-sm text-slate-300">A percentage of your downline's daily NFT bonus, credited automatically each day.</p>
                </div>
            </div>

            <div class="nx-coin-scene hidden h-32 w-32 shrink-0 md:block">
                <div class="nx-coin-flip">
                    <span class="nx-coin nx-coin-face"><img src="{{ asset('coin_nexadon.png') }}" alt="Nexadon coin" class="h-full w-full scale-[1.06] object-cover"></span>
                    <span class="nx-coin nx-coin-face nx-coin-back" aria-hidden="true"><img src="{{ asset('coin_nexadon.png') }}" alt="" class="h-full w-full scale-[1.06] object-cover"></span>
                </div>
            </div>
        </div>
    </section>

    {{-- Stat cards --}}
    <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ($stats as $stat)
            <div class="nx-panel flex items-center gap-4 p-5">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border {{ $stat['tile'] }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-300">{{ $stat['label'] }}</p>
                    <p class="mt-1 flex items-center gap-2.5 text-2xl font-bold text-white">
                        {{ $stat['value'] }}
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-nx-gold/50 bg-nx-gold/10 px-2 py-0.5 text-xs font-semibold text-nx-gold">{!! $coin !!} NXD</span>
                    </p>
                    <p class="mt-1 text-xs text-slate-400">{{ $stat['hint'] }}</p>
                </div>
            </div>
        @endforeach
    </section>

    {{-- Income history --}}
    <section id="history" class="nx-panel scroll-mt-28 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-6 py-4">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3M3.05 11a9 9 0 11.5 4M3 4v5h5" /></svg>
                Income History
            </h3>

                       <form method="GET" action="{{ route('referral-income') }}#history" class="flex flex-wrap items-center gap-3">
                {{-- Search --}}
                <div style="position:relative;">
                    <label for="income-search" class="sr-only">Search by name or user ID</label>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                    <input id="income-search" type="search" name="search" value="{{ $search }}" placeholder="Search by name or ID…"
                        style="width:16rem;padding:.625rem 1rem .625rem 2.75rem;"
                        class="rounded-xl border border-white/15 bg-nx-panel text-sm text-white placeholder:text-slate-500 hover:border-nx-gold/60 focus:border-nx-gold/70 focus:outline-none">
                </div>

                {{-- Date range --}}
                <div style="position:relative;">
                    <label for="income-range" class="sr-only">Date range</label>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#cbd5e1;pointer-events:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z" /></svg>
                    <select id="income-range" name="range" onchange="this.form.submit()"
                        style="min-width:11rem;padding:.625rem 2.5rem .625rem 2.75rem;-webkit-appearance:none;appearance:none;color-scheme:dark;"
                        class="cursor-pointer rounded-xl border border-emerald-400/40 bg-nx-panel text-sm text-white hover:border-nx-gold/60 focus:border-nx-gold/70 focus:outline-none">
                        <option value="" style="background:#07150e;color:#fff;">All Dates</option>
                        @foreach (\App\Http\Controllers\ReferralIncomeController::DATE_RANGES as $value => $label)
                            <option value="{{ $value }}" style="background:#07150e;color:#fff;" @selected($range === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#cbd5e1;pointer-events:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </div>

                @if ($search !== '' || $range)
                    <a href="{{ route('referral-income') }}#history" class="text-sm text-slate-400 hover:text-nx-gold">Clear</a>
                @endif
            </form>
        </div>

        @if ($incomes->isEmpty())
            <p class="px-6 py-12 text-center text-sm text-slate-400">
                @if ($search !== '' || $range)
                    No referral income matches these filters.
                @else
                    No referral income yet. It's earned automatically when someone in your downline receives a daily NFT bonus.
                @endif
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-225 text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 bg-white/3 text-slate-200">
                            <th class="px-6 py-3.5 font-semibold">Date</th>
                            <th class="px-4 py-3.5 font-semibold">From</th>
                            <th class="px-4 py-3.5 font-semibold">Level</th>
                            <th class="px-4 py-3.5 font-semibold">Percentage</th>
                            <th class="px-4 py-3.5 font-semibold">Base Amount</th>
                            <th class="px-6 py-3.5 font-semibold">Earned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($incomes as $income)
                            <tr class="transition hover:bg-white/3">
                                <td class="px-6 py-3 whitespace-nowrap text-slate-300">{{ $income->created_at->format('d M Y, h:i A') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="text-white">{{ $income->sourceUser->name }}</span>
                                    <span class="text-sky-400/80">({{ $income->sourceUser->user_id }})</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex min-w-10 justify-center rounded-lg px-2 py-1 text-xs font-bold ring-1 {{ $income->level == 1 ? 'bg-emerald-600/40 text-emerald-200 ring-emerald-400/40' : 'bg-sky-600/30 text-sky-200 ring-sky-400/40' }}">
                                        L{{ $income->level }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-200">{{ number_format((float) $income->percentage, 2) }}%</td>
                                <td class="px-4 py-3 text-slate-200">{{ $nxd($income->base_amount) }}</td>
                                <td class="px-6 py-3 font-semibold text-emerald-400">+{{ $nxd($income->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-6 py-4">
                <p class="text-sm text-slate-400">
                    Showing <span class="text-white">{{ $incomes->firstItem() }}</span>–<span class="text-white">{{ $incomes->lastItem() }}</span>
                    of <span class="text-white">{{ $incomes->total() }}</span> entries
                </p>
                {{ $incomes->fragment('history')->links('pagination.nx') }}
            </div>
        @endif
    </section>
</div>f
@endsection
