@extends('layouts.dashboard', ['title' => 'Royalty Bonus', 'dark' => true])

@php
    $nxd = fn ($value, $dec = 2) => number_format((float) $value, $dec);
    $benefit = config('royalty_bonus.profit_share_percent', 5);
    $months = config('royalty_bonus.window_months', 15);
    $thresholdF = (float) $threshold;
    $progressF = (float) $progress;
    $trackPct = $qualification ? 100 : $progressF;
    $milestones = [0, 25, 50, 75, 100];

    $badgeOk = 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300';
    $badgeNo = 'border-rose-500/60 bg-rose-500/10 text-rose-300';
    $badgeOff = 'border-slate-400/40 bg-white/5 text-slate-300';
    $clock = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
    $check = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
    $alert = 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';

    if ($qualification) {
        $statusLabel = $windowActive ? 'Qualified' : 'Window Expired';
        $statusBadge = $windowActive ? $badgeOk : $badgeOff;
        $statusIcon = $windowActive ? $check : $clock;
    } else {
        $statusLabel = 'Not Achieved';
        $statusBadge = $badgeNo;
        $statusIcon = $alert;
    }

    $rows = collect();
    if ($qualification) {
        $rows->push([
            'month' => $qualification->qualified_at->format('F Y'),
            'business' => $qualification->direct_business,
            'qualified' => true,
        ]);
    } else {
        $rows->push([
            'month' => $periodStart->format('F Y'),
            'business' => $currentBusiness,
            'qualified' => false,
        ]);
    }
@endphp

@section('content')
<style>
    .roy-hero { display: flex; flex-direction: column; gap: 1.5rem; }
    .roy-main { display: flex; align-items: center; gap: 1.25rem; min-width: 0; }
    .roy-body { flex: 1 1 0%; min-width: 0; }
    .roy-status { width: 100%; }
    .roy-coin { display: none; width: 9rem; height: 9rem; flex-shrink: 0; }
    @media (min-width: 1024px) {
        .roy-hero { display: grid; grid-template-columns: minmax(0, 1fr) 20rem; align-items: center; }
        .roy-status { width: auto; }
    }
    @media (min-width: 1280px) {
        .roy-coin { display: block; }
    }
</style>

<div class="space-y-5 text-slate-200">

    {{-- Hero --}}
    <section class="nx-panel nx-panel-gold overflow-hidden">
        <div class="nx-stars pointer-events-none absolute inset-0"></div>
        <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
        <div class="pointer-events-none absolute right-72 top-1/2 h-72 w-72 -translate-y-1/2 rounded-full bg-nx-gold/15 blur-3xl"></div>

        <div class="roy-hero relative p-5 sm:px-6">
            <div class="roy-main">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl sm:h-20 sm:w-20 border border-nx-gold/60 bg-nx-gold/10 text-nx-gold shadow-[0_0_20px_-8px] shadow-nx-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 sm:h-11 sm:w-11" viewBox="0 0 24 24" fill="currentColor"><path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8zm2.6 12h12.8v1.5H5.6V20z"/></svg>
                </span>

                <div class="roy-body">
                    <p class="font-medium text-white">Direct Business Monthly ({{ $periodStart->format('F Y') }})</p>
                    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                        <p class="nx-gold-text text-3xl font-bold whitespace-nowrap sm:text-5xl">{{ $nxd($threshold, 0) }} <span class="text-2xl">NXD</span></p>
                        <span class="text-2xl text-slate-300">&rarr;</span>
                        <p class="nx-gold-text text-3xl font-bold whitespace-nowrap sm:text-5xl">{{ $benefit }}%</p>
                        <p class="text-sm leading-snug">
                            <span class="block font-semibold text-white">Global Turnover Bonus Distribution</span>
                            <span class="block text-xs text-slate-400">for the next {{ $months }} months.</span>
                        </p>
                    </div>
                    <p class="mt-3 max-w-2xl text-sm text-slate-300">
                        One-time lifetime qualification &mdash; reaching the threshold again later doesn't open another window.
                        The pool's payout split among eligible users is still being finalized with the client.
                    </p>
                </div>

                {{-- Nexadon coin --}}
                <div class="roy-coin nx-coin-scene">
                    <div class="nx-coin-flip">
                        <span class="nx-coin nx-coin-face"><img src="{{ asset('coin_nexadon.png') }}" alt="Nexadon coin" class="h-full w-full scale-[1.06] object-cover"></span>
                        <span class="nx-coin nx-coin-face nx-coin-back" aria-hidden="true"><img src="{{ asset('coin_nexadon.png') }}" alt="" class="h-full w-full scale-[1.06] object-cover"></span>
                    </div>
                </div>
            </div>

            {{-- Status --}}
            <div class="roy-status rounded-xl border border-white/10 bg-black/30 p-5">
                <p class="mb-2 text-sm text-slate-300">Qualification Status</p>
                <span class="inline-flex items-center gap-2 rounded-lg border px-3.5 py-1.5 text-base font-semibold {{ $statusBadge }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $statusIcon }}"/></svg>
                    {{ $statusLabel }}
                </span>
                <p class="mt-3 text-sm text-slate-200">
                    @if ($qualification)
                        Window: <span class="font-semibold text-white">{{ $qualification->window_start->format('d M Y') }} &ndash; {{ $qualification->window_end->format('d M Y') }}</span><br>
                        <span class="text-xs text-slate-400">Qualified with {{ $nxd($qualification->direct_business) }} Direct Business in {{ $qualification->qualified_at->format('F Y') }}.</span>
                    @else
                        Reach {{ $nxd($threshold, 0) }} in Direct Monthly Business to qualify for {{ $benefit }}% company-wide profit-sharing distribution.
                    @endif
                </p>
            </div>
        </div>
    </section>

    {{-- Stat cards --}}
    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="nx-panel flex items-center gap-4 p-5">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-emerald-400/50 bg-emerald-400/10 text-emerald-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm text-slate-300">Monthly Target</p>
                <p class="mt-1 text-3xl font-bold text-emerald-400">{{ $nxd($threshold, 0) }}</p>
                <p class="mt-1 text-xs text-slate-400">Direct business required</p>
            </div>
        </div>

        <div class="nx-panel flex items-center gap-4 p-5">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-emerald-400/50 bg-emerald-400/10 text-emerald-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" viewBox="0 0 24 24" fill="currentColor"><path d="M4 20V11h4v9H4zm6 0V4h4v16h-4zm6 0v-6h4v6h-4z"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm text-slate-300">Current Business</p>
                <p class="mt-1 text-3xl font-bold text-emerald-400">{{ $nxd($currentBusiness) }}</p>
                <p class="mt-1 text-xs text-slate-400">This month ({{ $periodStart->format('F Y') }})</p>
            </div>
        </div>

        <div class="nx-panel nx-panel-gold flex items-center gap-4 p-5">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-nx-gold/60 bg-nx-gold/10 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" viewBox="0 0 24 24" fill="currentColor"><path d="M11 2.05V13h10.95A10 10 0 0011 2.05zM9 4.06A10 10 0 1019.94 15H9V4.06z"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm text-slate-300">Progress</p>
                <p class="mt-1 text-3xl font-bold text-emerald-400">{{ $nxd($progressF) }}%</p>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-linear-to-r from-emerald-500 to-nx-gold" style="width: {{ min(100, $progressF) }}%"></div>
                </div>
                <p class="mt-1 text-xs text-slate-400">{{ $nxd($currentBusiness, 0) }} / {{ $nxd($threshold, 0) }}</p>
            </div>
        </div>

        <div class="nx-panel nx-panel-gold flex items-center gap-4 p-5">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-nx-gold/60 bg-nx-gold/10 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12v9H4v-9m16 0H4m16 0V8H4v4m8-4v13M12 8c-2-3-6-3-6-1s3 1 6 1zm0 0c2-3 6-3 6-1s-3 1-6 1z"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm text-slate-300">Expected Benefit</p>
                <p class="mt-1 text-3xl font-bold text-white">{{ $benefit }}%</p>
                <p class="mt-1 text-xs text-slate-400">Global turnover bonus distribution</p>
            </div>
        </div>
    </section>

    {{-- Qualification progress --}}
    <section class="nx-panel p-5">
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M5 3h2v18H5V3zm3 1h11l-2 4 2 4H8V4z"/></svg>
                Qualification Progress (Monthly)
            </h3>
            <span class="rounded-full border border-emerald-400/40 bg-emerald-400/10 px-3 py-0.5 text-xs text-emerald-300">Target: {{ $nxd($threshold, 0) }} NXD</span>
        </div>

        <div class="px-6 sm:px-12">
            <div class="relative h-8">
                <div class="absolute top-1/2 right-0 left-0 h-1.5 -translate-y-1/2 rounded-full bg-white/10"></div>
                <div class="absolute top-1/2 left-0 h-1.5 -translate-y-1/2 rounded-full bg-linear-to-r from-emerald-500 to-nx-gold" style="width: {{ min(100, $trackPct) }}%"></div>

                @foreach ($milestones as $m)
                    @php $reached = $m === 0 || $trackPct >= $m; @endphp
                    <div class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2" style="left: {{ $m }}%">
                        @if ($m === 100)
                            <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-nx-gold {{ $reached ? 'bg-nx-gold text-black' : 'bg-nx-panel text-nx-gold' }} shadow-[0_0_14px_-2px] shadow-nx-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8z"/></svg>
                            </span>
                        @else
                            <span class="block h-6 w-6 rounded-full border-2 {{ $reached ? 'border-emerald-400 bg-emerald-400' : 'border-emerald-300/70 bg-nx-panel' }}"></span>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="relative mt-3 h-16">
                @foreach ($milestones as $m)
                    @php
                        $first = $m === 0;
                        $last = $m === 100;
                        $pos = $first ? 'left:-12px' : ($last ? 'right:-16px' : 'left:'.$m.'%');
                        $cls = $first ? 'items-start text-left' : ($last ? 'items-end text-right' : '-translate-x-1/2 items-center text-center');
                    @endphp
                    <div class="absolute top-0 flex flex-col whitespace-nowrap {{ $cls }}" style="{{ $pos }}">
                        <p class="text-sm font-semibold text-white">{{ $first ? '0' : $nxd($thresholdF * $m / 100, 0) }}</p>
                        <p class="text-xs text-slate-400">{{ $first ? 'Start' : $m.'%' }}</p>
                        @if ($last)
                            <p class="text-xs {{ $qualification ? 'text-nx-gold' : 'text-slate-400' }}">
                                {{ $qualification ? 'Qualified for' : 'Qualify for' }} {{ $benefit }}% Bonus
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4 flex items-start gap-4 rounded-xl border border-emerald-400/25 bg-emerald-400/5 p-4 text-sm text-slate-200">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-400 text-black">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11 10h2v8h-2v-8zm0-4h2v2h-2V6z"/></svg>
            </span>
            <p>
                Reach {{ $nxd($threshold, 0) }} in Direct Monthly Business in any calendar month to open your {{ $months }}-month Royalty window.
                This is checked once a day, so it may take up to a day to reflect after you cross the threshold.
            </p>
        </div>
    </section>

    {{-- Monthly history --}}
    <section class="nx-panel nx-panel-gold overflow-hidden">
        <div class="flex items-center gap-4 px-6 pt-5 pb-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-nx-gold/60 bg-nx-gold/10 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
            </span>
            <div>
                <h3 class="text-lg font-semibold text-white">Monthly History</h3>
                <p class="text-sm text-slate-400">Track your direct monthly business and qualification status.</p>
            </div>
        </div>

        <div class="overflow-x-auto px-3 pb-3">
            <table class="w-full min-w-225 text-left text-sm">
                <thead>
                    <tr class="bg-emerald-950/60 text-slate-200">
                        <th class="px-4 py-3 font-medium">Month</th>
                        <th class="px-4 py-3 font-medium">Direct Business (NXD)</th>
                        <th class="px-4 py-3 font-medium">Target</th>
                        <th class="px-4 py-3 font-medium">Progress</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Expected Benefit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach ($rows as $row)
                        @php $rowPct = $thresholdF > 0 ? min(100, (float) $row['business'] / $thresholdF * 100) : 0; @endphp
                        <tr class="text-slate-200 transition hover:bg-white/3">
                            <td class="px-4 py-3 font-medium whitespace-nowrap text-white">{{ $row['month'] }}</td>
                            <td class="px-4 py-3">{{ $nxd($row['business']) }}</td>
                            <td class="px-4 py-3">{{ $nxd($threshold, 0) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-14 text-xs">{{ $nxd($rowPct) }}%</span>
                                    <div class="h-2 w-44 overflow-hidden rounded-full bg-white/10">
                                        <div class="h-full rounded-full bg-linear-to-r from-emerald-500 to-nx-gold" style="width: {{ $rowPct }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $row['qualified'] ? $badgeOk : $badgeNo }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $row['qualified'] ? $check : $alert }}"/></svg>
                                    {{ $row['qualified'] ? 'Qualified' : 'Not Qualified Yet' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $benefit }}% (Not Credited)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection