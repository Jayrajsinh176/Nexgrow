@extends('layouts.dashboard', ['title' => '15-Day Bonus Booster', 'dark' => true])

@php
    $nxd = fn ($value) => number_format((float) $value, 2);

    // Badge colours by tier (lowest first): [light, dark, glow, text].
    $badges = [
        ['#f59e6b', '#9a3412', 'rgb(249 115 22 / 0.35)', 'text-orange-300'],
        ['#e2e8f0', '#475569', 'rgb(203 213 225 / 0.3)', 'text-slate-200'],
        ['#6ee7b7', '#047857', 'rgb(34 197 94 / 0.35)', 'text-emerald-300'],
        ['#7dd3fc', '#1d4ed8', 'rgb(59 130 246 / 0.35)', 'text-sky-300'],
        ['#d8b4fe', '#6d28d9', 'rgb(139 92 246 / 0.35)', 'text-violet-300'],
        ['#fde68a', '#b45309', 'rgb(242 193 78 / 0.45)', 'text-nx-gold'],
        ['#fca5a5', '#b91c1c', 'rgb(239 68 68 / 0.35)', 'text-rose-300'],
        ['#f8fafc', '#64748b', 'rgb(226 232 240 / 0.35)', 'text-white'],
    ];

    // Whole days + hours left, instead of a fractional day count.
    $secondsLeft = max(0, now()->diffInSeconds($periodEnd, false));
    $daysLeft = intdiv((int) $secondsLeft, 86400);
    $hoursLeft = intdiv((int) $secondsLeft % 86400, 3600);
    $timeLeft = $secondsLeft <= 0
        ? 'Closing now'
        : ($daysLeft > 0 ? $daysLeft.' '.str('day')->plural($daysLeft).' ' : '').$hoursLeft.' '.str('hour')->plural($hoursLeft).' left';
@endphp

@section('content')
<div class="space-y-5 text-slate-200">

    {{-- Current window / Current tier --}}
    <section class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_22rem]">
        <div class="nx-panel nx-panel-gold overflow-hidden">
            <div class="nx-stars pointer-events-none absolute inset-0"></div>
            <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
            <div class="pointer-events-none absolute top-1/2 right-16 h-64 w-64 -translate-y-1/2 rounded-full bg-emerald-500/20 blur-3xl"></div>

            <div class="relative flex items-center justify-between gap-6 p-6 sm:px-8">
                <div class="flex min-w-0 items-start gap-4 sm:gap-5">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border border-nx-gold/60 bg-nx-gold/10 text-nx-gold shadow-[0_0_20px_-6px] shadow-nx-gold">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" /></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-lg text-white">Current Window Team Business</p>
                        <div class="mt-2 flex flex-wrap items-center gap-4">
                            <p class="nx-gold-text text-3xl font-bold break-words sm:text-5xl">{{ $nxd($currentBusiness) }}</p>
                            <span class="inline-flex items-center gap-2 rounded-full border border-nx-gold/60 bg-nx-gold/10 px-3 py-1 text-sm font-semibold text-nx-gold">
                                <span class="nx-coin block h-5 w-5"><img src="{{ asset('coin_nexadon.png') }}" alt="" class="h-full w-full scale-[1.06] object-cover"></span>
                                NXD
                            </span>
                        </div>
                        <p class="mt-3 flex flex-wrap items-center gap-x-2 text-sm text-slate-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z" /></svg>
                            Window: {{ $periodStart->format('d M Y') }} – {{ $periodEnd->format('d M Y') }}
                            <span class="font-medium text-nx-gold">({{ $timeLeft }})</span>
                        </p>
                        <p class="mt-1 text-xs text-slate-400">NFT purchases by you and your whole downline during this window.</p>
                    </div>
                </div>

                {{-- Lightning over rising bars --}}
                <svg class="hidden h-36 w-52 shrink-0 lg:block" viewBox="0 0 200 140" aria-hidden="true">
                    <defs>
                        <linearGradient id="boltFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffe39a" /><stop offset="1" stop-color="#b8862b" /></linearGradient>
                        <linearGradient id="barFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4ade80" /><stop offset="1" stop-color="#14532d" stop-opacity=".2" /></linearGradient>
                    </defs>
                    @foreach ([[12, 70], [34, 52], [140, 58], [162, 40], [184, 62]] as [$x, $y])
                        <rect x="{{ $x }}" y="{{ $y }}" width="12" height="{{ 118 - $y }}" rx="2" fill="url(#barFill)" opacity=".8" />
                    @endforeach
                    <ellipse cx="100" cy="120" rx="58" ry="12" fill="#22c55e" opacity=".25" />
                    <ellipse cx="100" cy="116" rx="40" ry="8" fill="none" stroke="#f2c14e" stroke-opacity=".7" stroke-width="2" />
                    <ellipse cx="100" cy="116" rx="26" ry="5" fill="#f2c14e" opacity=".35" />
                    <path d="M112 8L74 70h24l-10 42 40-64h-26l10-40z" fill="url(#boltFill)" stroke="#fff3c4" stroke-width="1.5" style="filter: drop-shadow(0 0 12px rgb(242 193 78 / 0.8))" />
                </svg>
            </div>
        </div>

        <div class="nx-panel nx-panel-gold flex flex-col justify-center p-6">
            <p class="flex items-center gap-3 text-lg text-white">
                <span class="flex h-10 w-10 items-center justify-center rounded-full border border-nx-gold/60 bg-nx-gold/10 text-nx-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8m-4-4v4M7 4h10v5a5 5 0 01-10 0V4zm0 2H4v1a3 3 0 003 3m10-4h3v1a3 3 0 01-3 3" /></svg>
                </span>
                Current Tier
            </p>
            <p class="mt-3 text-5xl font-bold {{ $currentPercentage > 0 ? 'nx-gold-text' : 'text-slate-500' }}">{{ $currentPercentage }}%</p>
            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-white/10">
                <div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-nx-gold shadow-[0_0_10px] shadow-nx-gold/60" style="width: {{ round($progress, 1) }}%"></div>
            </div>
            <p class="mt-2 text-xs text-slate-400">
                @if ($nextTier)
                    {{ $nxd($nextTier['business'] - (float) $currentBusiness) }} NXD more to reach {{ $nextTier['percentage'] }}%
                @else
                    Top tier reached
                @endif
            </p>
        </div>
    </section>

    {{-- Tier benefits --}}
    <section class="nx-panel p-5 sm:p-6">
        <div class="flex items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-nx-gold/60 bg-nx-gold/10 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3zm-3 9l2 2 4-4" /></svg>
            </span>
            <div>
                <h3 class="text-lg font-semibold text-white">Tier Benefits</h3>
                <p class="text-sm text-slate-300">Higher team business in a window unlocks a higher bonus percentage.</p>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 2xl:grid-cols-8">
            @foreach ($tiers as $tier)
                @php
                    [$light, $dark, $glow, $text] = $badges[$loop->index];
                    $isCurrent = $currentTier && $currentTier['business'] === $tier['business'];
                    $reached = (float) $currentBusiness >= $tier['business'];
                @endphp
                <div class="relative flex items-center gap-3 rounded-xl border p-3 transition {{ $isCurrent ? 'border-nx-gold bg-nx-gold/10 shadow-[0_0_24px_-6px] shadow-nx-gold' : ($reached ? 'border-white/20 bg-white/5' : 'border-white/10 bg-black/20 opacity-70') }}"
                    style="{{ $isCurrent ? '' : 'background-image: radial-gradient(90% 90% at 0% 0%, '.$glow.', transparent 70%)' }}">
                    @if ($isCurrent)
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-nx-gold px-2.5 py-0.5 text-[11px] font-semibold whitespace-nowrap text-black">Current Tier</span>
                    @endif
                    <svg class="h-11 w-11 shrink-0" viewBox="0 0 48 48" style="filter: drop-shadow(0 0 8px {{ $glow }})" aria-hidden="true">
                        <defs>
                            <linearGradient id="badge-{{ $loop->index }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{{ $light }}" /><stop offset="1" stop-color="{{ $dark }}" /></linearGradient>
                        </defs>
                        <path d="M24 3l17 6v12c0 11-7.5 19.5-17 24C14.5 40.5 7 32 7 21V9l17-6z" fill="#0b0b0b" stroke="url(#badge-{{ $loop->index }})" stroke-width="3" />
                        <path d="M24 12l3.1 6.3 6.9 1-5 4.9 1.2 6.8L24 27.8 17.8 31l1.2-6.8-5-4.9 6.9-1L24 12z" fill="url(#badge-{{ $loop->index }})" />
                    </svg>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-white">{{ number_format($tier['business']) }}</p>
                        <p class="text-2xl font-bold {{ $text }}">{{ $tier['percentage'] }}%</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Cycle history --}}
    <section id="history" class="nx-panel scroll-mt-28 overflow-hidden">
        <div class="flex items-start gap-4 border-b border-white/10 px-6 py-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-nx-gold/60 bg-nx-gold/10 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3M3.05 11a9 9 0 11.5 4M3 4v5h5" /></svg>
            </span>
            <div>
                <h3 class="text-lg font-semibold text-white">Cycle History</h3>
                <p class="text-sm text-slate-300">The opportunity refreshes every 15 days.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-200 text-left text-sm">
                <thead>
                    <tr class="border-b border-white/10 bg-white/3 text-slate-200">
                        <th class="px-6 py-3.5 font-semibold">Period</th>
                        <th class="px-4 py-3.5 font-semibold">Team Business (NXD)</th>
                        <th class="px-4 py-3.5 font-semibold">Tier</th>
                        <th class="px-4 py-3.5 font-semibold">Bonus (NXD)</th>
                        <th class="px-6 py-3.5 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @if ($history->onFirstPage())
                        <tr class="bg-nx-gold/5">
                            <td class="px-6 py-3 whitespace-nowrap text-white">{{ $periodStart->format('d M') }} – {{ $periodEnd->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-slate-200">{{ $nxd($currentBusiness) }}</td>
                            <td class="px-4 py-3 text-slate-200">{{ number_format((float) $currentPercentage, 2) }}%</td>
                            <td class="px-4 py-3 font-semibold {{ $currentPercentage > 0 ? 'text-emerald-400' : 'text-slate-500' }}">
                                {{ $currentPercentage > 0 ? '+'.$nxd($estimatedBonus) : '—' }}
                                @if ($currentPercentage > 0)<span class="ml-1 text-xs font-normal text-slate-400">est.</span>@endif
                            </td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-nx-gold/60 bg-nx-gold/10 px-3 py-1 text-xs font-semibold text-nx-gold">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    In Progress
                                </span>
                            </td>
                        </tr>
                    @endif

                    @foreach ($history as $cycle)
                        <tr class="transition hover:bg-white/3">
                            <td class="px-6 py-3 whitespace-nowrap text-slate-300">{{ $cycle->period_start->format('d M') }} – {{ $cycle->period_end->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-slate-200">{{ $nxd($cycle->business_amount) }}</td>
                            <td class="px-4 py-3 text-slate-200">{{ number_format((float) $cycle->percentage, 2) }}%</td>
                            <td class="px-4 py-3 font-semibold {{ $cycle->qualified ? 'text-emerald-400' : 'text-slate-500' }}">
                                {{ $cycle->qualified ? '+'.$nxd($cycle->bonus_amount) : '—' }}
                            </td>
                            <td class="px-6 py-3">
                                @if ($cycle->qualified)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/50 bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 2a10 10 0 100 20 10 10 0 000-20zm4.7 7.7l-5.5 5.5a1 1 0 01-1.4 0l-2.5-2.5a1 1 0 111.4-1.4l1.8 1.8 4.8-4.8a1 1 0 111.4 1.4z" clip-rule="evenodd" /></svg>
                                        Qualified
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-slate-400">Not qualified</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($history->isEmpty())
            <p class="border-t border-white/5 px-6 py-4 text-center text-sm text-slate-400">No completed cycles yet. Your first window closes on {{ $periodEnd->format('d M Y') }}.</p>
        @elseif ($history->hasPages())
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-6 py-4">
                <p class="text-sm text-slate-400">
                    Showing <span class="text-white">{{ $history->firstItem() }}</span>–<span class="text-white">{{ $history->lastItem() }}</span>
                    of <span class="text-white">{{ $history->total() }}</span> completed cycles
                </p>
                {{ $history->links('pagination.nx') }}
            </div>
        @endif
    </section>
</div>
@endsection
