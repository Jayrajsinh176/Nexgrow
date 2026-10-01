@extends('layouts.dashboard', ['title' => 'Team Development Bonus', 'dark' => true])

@php
    $nxd = fn ($value, $dec = 2) => number_format((float) $value, $dec);
    $benefit = config('team_development_bonus.extra_bonus_percent', 5);
    $months = config('team_development_bonus.window_months', 12);
    $teamCount = max(1, count($teams));
    $totalRequired = (float) $requiredPerTeam * $teamCount;
    $heroImage = file_exists(public_path('team-dev-hero.png')) ? asset('team-dev-hero.png') : null;

    $check = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
    $clock = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
    $usersIcon = 'M9 11a3 3 0 100-6 3 3 0 000 6zm6 0a3 3 0 100-6 3 3 0 000 6zM9 13c-2.7 0-6 1.3-6 4v2h12v-2c0-2.7-3.3-4-6-4zm6 0c-.4 0-.8 0-1.2.1 1.2.9 2.2 2.2 2.2 3.9v2h5v-2c0-2.7-3.3-4-6-4z';
@endphp

@section('content')
<style>
    .tdb-hero { display: flex; flex-wrap: wrap; align-items: center; gap: 1.25rem; }
    .tdb-hero-body { flex: 1 1 22rem; min-width: 0; }
    .tdb-hero-art { display: none; }
    @media (min-width: 1280px) {
        .tdb-hero-art { display: block; position: absolute; right: 0; bottom: 0; height: 100%; max-width: 40%; object-fit: contain; object-position: right bottom; pointer-events: none; }
    }
</style>

<div class="space-y-5 text-slate-200">

    {{-- Hero --}}
    <section class="nx-panel nx-panel-gold overflow-hidden">
        <div class="nx-stars pointer-events-none absolute inset-0"></div>
        <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
        <div class="pointer-events-none absolute right-40 top-1/2 h-72 w-72 -translate-y-1/2 rounded-full bg-nx-gold/15 blur-3xl"></div>
        @if ($heroImage)
            <img src="{{ $heroImage }}" alt="" class="tdb-hero-art">
        @endif

        <div class="tdb-hero relative p-5 sm:px-6">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl sm:h-20 sm:w-20 border border-nx-gold/60 bg-nx-gold/10 text-nx-gold shadow-[0_0_20px_-8px] shadow-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 sm:h-11 sm:w-11" viewBox="0 0 24 24" fill="currentColor"><path d="{{ $usersIcon }}"/></svg>
            </span>

            <div class="tdb-hero-body">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="text-lg text-white">Target:</span>
                    <span class="nx-gold-text text-3xl font-bold break-words sm:text-5xl">{{ $nxd($totalRequired, 0) }}</span>
                    <span class="text-lg font-semibold text-white">business across {{ $teamCount }} teams</span>
                    <span class="text-xl text-emerald-400">&rarr;</span>
                    <span class="nx-gold-text text-3xl font-bold sm:text-4xl">{{ $benefit }}%</span>
                    <span class="text-lg font-semibold text-white">Extra Bonus</span>
                </div>
                <p class="mt-2 text-base text-slate-200">
                    Each of your top {{ $teamCount }} direct-referral teams must independently reach {{ $nxd($requiredPerTeam, 0) }} in lifetime team business.
                </p>
                <p class="mt-2 max-w-3xl text-xs text-slate-400">
                    One-time lifetime qualification; reaching it again later doesn't open another window.
                    The extra-bonus payout mechanics are still being finalized with the client; this page tracks qualification and window status only.
                </p>
            </div>
        </div>
    </section>

    {{-- Window (only once qualified) --}}
    @if ($qualification)
        <section class="nx-panel nx-panel-gold p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-300">Team Development Window</p>
                    <p class="mt-1 text-xl font-semibold text-white">
                        {{ $qualification->window_start->format('d M Y') }} &ndash; {{ $qualification->window_end->format('d M Y') }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                        Qualified with {{ $nxd($qualification->team_business) }} combined team business on {{ $qualification->qualified_at->format('d M Y') }}.
                    </p>
                </div>
                <span class="inline-flex items-center gap-2 rounded-lg border px-3.5 py-1.5 text-base font-semibold {{ $windowActive ? 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300' : 'border-slate-400/40 bg-white/5 text-slate-300' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $windowActive ? $check : $clock }}"/></svg>
                    {{ $windowActive ? 'Active' : 'Expired' }}
                </span>
            </div>
        </section>
    @endif

    {{-- Qualifying teams --}}
    <section class="nx-panel p-5">
        <h3 class="mb-4 flex items-center gap-3 text-lg font-semibold text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="{{ $usersIcon }}"/></svg>
            Qualifying Teams (each needs {{ $nxd($requiredPerTeam, 0) }})
        </h3>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach ($teams as $team)
                @php
                    $pct = min(max((float) $team['progress'], 0), 100);
                    $first = $loop->first && ! $team['met'];
                @endphp
                <div class="flex gap-4 rounded-xl border p-4 {{ $team['met']
                    ? 'border-emerald-400 bg-emerald-400/10 shadow-[0_0_18px_-4px] shadow-emerald-400'
                    : ($first ? 'border-nx-gold/60 bg-nx-gold/5' : 'border-white/15 bg-black/30') }}">
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border {{ $first || $team['met'] ? 'border-nx-gold/60 bg-nx-gold/10 text-nx-gold' : 'border-white/20 bg-white/5 text-slate-300' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" viewBox="0 0 24 24" fill="currentColor"><path d="{{ $usersIcon }}"/></svg>
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs uppercase tracking-wide {{ $first ? 'text-nx-gold' : 'text-slate-300' }}">Team {{ $loop->iteration }}</p>
                                <p class="truncate text-sm font-semibold text-white">{{ $team['name'] ?? 'No team yet' }}</p>
                            </div>
                            @if ($team['met'])
                                <span class="rounded-full bg-emerald-400 px-2.5 py-0.5 text-xs font-bold text-black">Met</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full border {{ $first ? 'border-nx-gold/70 text-nx-gold' : 'border-white/40 text-white' }} px-2 py-0.5 text-xs">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span> In Progress
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 text-xs text-slate-300">Required: {{ $nxd($team['required']) }}</p>
                        <p class="text-xs text-slate-300">Volume: {{ $nxd($team['business_volume']) }}</p>

                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full {{ $team['met'] ? 'bg-emerald-400' : 'bg-linear-to-r from-emerald-500 to-nx-gold' }}" style="width: {{ $pct }}%"></div>
                        </div>
                        <p class="mt-1 text-sm font-semibold {{ $team['met'] ? 'text-emerald-400' : 'text-nx-gold' }}">{{ $nxd($team['progress'], 1) }}%</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex items-start gap-4 rounded-xl border border-emerald-400/25 bg-emerald-400/5 p-4 text-sm text-slate-200">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-400 text-black">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11 10h2v8h-2v-8zm0-4h2v2h-2V6z"/></svg>
            </span>
            <p>
                Each of your top {{ $teamCount }} direct-referral teams must independently reach {{ $nxd($requiredPerTeam, 0) }} in lifetime team business &mdash; one team's surplus can't cover another's shortfall.
                One-time lifetime qualification; reaching it again later doesn't open another window.
                The extra-bonus payout mechanics are still being finalized with the client; this page tracks qualification and window status only.
            </p>
        </div>
    </section>

    {{-- Overview --}}
    <section class="nx-panel nx-panel-gold overflow-hidden">
        <div class="px-6 pt-5 pb-3">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M4 20V10h4v10H4zm6 0V4h4v16h-4zm6 0v-7h4v7h-4z"/></svg>
                Team Progress Overview
            </h3>
        </div>

        <div class="overflow-x-auto px-3 pb-3">
            <table class="w-full min-w-225 text-left text-sm">
                <thead>
                    <tr class="bg-emerald-950/60 text-slate-200">
                        <th class="px-4 py-3 font-medium text-nx-gold">Team</th>
                        <th class="px-4 py-3 font-medium text-nx-gold">Required Business</th>
                        <th class="px-4 py-3 font-medium text-nx-gold">Current Business</th>
                        <th class="px-4 py-3 font-medium text-nx-gold">Progress</th>
                        <th class="px-4 py-3 font-medium text-nx-gold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach ($teams as $team)
                        @php $pct = min(max((float) $team['progress'], 0), 100); @endphp
                        <tr class="text-slate-200 transition hover:bg-white/3">
                            <td class="px-4 py-3 font-medium whitespace-nowrap text-white">
                                <span class="mr-1.5 text-nx-gold">&#9733;</span>Team {{ $loop->iteration }}
                                @if ($team['name'])
                                    <span class="ml-1 text-xs font-normal text-slate-400">({{ $team['name'] }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $nxd($team['required']) }}</td>
                            <td class="px-4 py-3">{{ $nxd($team['business_volume']) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-4">
                                    <div class="h-2 w-52 overflow-hidden rounded-full bg-white/10">
                                        <div class="h-full rounded-full {{ $team['met'] ? 'bg-emerald-400' : 'bg-linear-to-r from-emerald-500 to-nx-gold' }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs">{{ $nxd($team['progress'], 1) }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($team['met'])
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/50 bg-emerald-400/10 px-2.5 py-0.5 text-xs font-medium text-emerald-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $check }}"/></svg>
                                        Qualified
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-nx-gold/60 bg-nx-gold/10 px-2.5 py-0.5 text-xs font-medium text-nx-gold">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $clock }}"/></svg>
                                        Not Qualified
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection